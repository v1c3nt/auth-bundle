<?php

namespace Vvbx\SuiteBundle\Tests\Security;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Vvbx\SuiteBundle\Doctrine\UserRepository;
use Vvbx\SuiteBundle\Oidc\OidcClient;
use Vvbx\SuiteBundle\Security\OidcAuthenticator;
use Vvbx\SuiteBundle\Security\OidcSubjectTrait;
use Vvbx\SuiteBundle\Security\OidcUserInterface;
use Vvbx\SuiteBundle\Testing\FakeIdp;

final class OidcAuthenticatorTest extends TestCase
{
    protected function setUp(): void
    {
        FakeIdp::reset();
    }

    public function testUnverifiedEmailGetsItsOwnMessageWithoutLookingUpAccounts(): void
    {
        FakeIdp::$claims = ['sub' => 'sub-1', 'email' => 'a@exemple.fr', 'email_verified' => false];

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturnCallback(static function (array $criteria): null {
            // ---- seule la recherche par sub est permise : jamais par e-mail
            self::assertSame(['oidcSubject'], array_keys($criteria));

            return null;
        });

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('oidc.error.email_unverified');
        $this->resolve($repository);
    }

    public function testMissingEmailClaimKeepsTheGenericMessage(): void
    {
        FakeIdp::$claims = ['sub' => 'sub-1'];

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('oidc.error.unknown_user');
        $this->resolve($this->createMock(EntityRepository::class));
    }

    public function testVerifiedEmailOfUnknownAccountKeepsTheGenericMessage(): void
    {
        FakeIdp::$claims = ['sub' => 'sub-1', 'email' => 'a@exemple.fr', 'email_verified' => true];

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('oidc.error.unknown_user');
        $this->resolve($this->createMock(EntityRepository::class));
    }

    public function testVerifiedEmailLinksPreauthorizedAccount(): void
    {
        FakeIdp::$claims = ['sub' => 'sub-1', 'email' => 'a@exemple.fr', 'email_verified' => true];

        $account = new class implements OidcUserInterface {
            use OidcSubjectTrait;

            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return 'a@exemple.fr';
            }
        };
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?OidcUserInterface => isset($criteria['oidcSubject']) ? null : $account);

        self::assertSame($account, $this->resolve($repository));
        self::assertSame('sub-1', $account->getOidcSubject());
    }

    /**
     * @param EntityRepository<object> $repository
     */
    private function resolve(EntityRepository $repository): OidcUserInterface
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://app.test/oidc/callback');

        $authenticator = new OidcAuthenticator(
            new OidcClient(new MockHttpClient(new FakeIdp()), new ArrayAdapter(), FakeIdp::ISSUER, 'app', 'secret'),
            new UserRepository($registry, OidcUserInterface::class, 'email'),
            $urls,
            'home',
            'login',
        );

        $session = new Session(new MockArraySessionStorage());
        $session->set(OidcAuthenticator::SESSION_KEY, ['state' => 'state-1', 'verifier' => 'verifier-1']);
        $request = Request::create('/oidc/callback', 'GET', ['state' => 'state-1', 'code' => 'code-1']);
        $request->setSession($session);

        $user = $authenticator->authenticate($request)->getBadge(\Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge::class)->getUser();
        \assert($user instanceof OidcUserInterface);

        return $user;
    }
}
