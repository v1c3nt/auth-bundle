<?php

namespace Vvbx\SuiteBundle\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Vvbx\SuiteBundle\Doctrine\UserRepository;
use Vvbx\SuiteBundle\Oidc\OidcClient;
use Vvbx\SuiteBundle\Oidc\OidcException;

/**
 * Retour de l'IdP sur /oidc/callback : vérifie le state, lit les claims et retrouve le compte.
 *
 * Le compte est rattaché au sub. À la première connexion, un compte existant sans sub est rattaché
 * par son e-mail vérifié (pré-autorisation par l'administrateur). Un inconnu n'a aucun accès.
 */
final class OidcAuthenticator extends AbstractAuthenticator
{
    public const SESSION_KEY = 'vvbx_suite.oidc.pending';
    public const CALLBACK_ROUTE = 'vvbx_suite_oidc_callback';

    public function __construct(
        private readonly OidcClient $oidc,
        private readonly UserRepository $users,
        private readonly UrlGeneratorInterface $urls,
        private readonly string $targetRoute,
        private readonly string $loginRoute,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $this->oidc->isEnabled() && self::CALLBACK_ROUTE === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        // ---- state et verifier à usage unique : retirés de la session quel que soit le résultat
        $pending = $request->getSession()->remove(self::SESSION_KEY);
        $state = $request->query->getString('state');
        if (!\is_array($pending) || !\is_string($pending['state'] ?? null) || !\is_string($pending['verifier'] ?? null)
            || '' === $state || !hash_equals($pending['state'], $state)) {
            throw new CustomUserMessageAuthenticationException('oidc.error.state');
        }

        $code = $request->query->getString('code');
        if ($request->query->has('error') || '' === $code) {
            throw new CustomUserMessageAuthenticationException('oidc.error.denied');
        }

        try {
            $claims = $this->oidc->fetchClaims($code, $this->redirectUri(), $pending['verifier']);
        } catch (OidcException $e) {
            throw new CustomUserMessageAuthenticationException('oidc.error.idp', [], 0, $e);
        }

        $subject = $claims['sub'] ?? null;
        if (!\is_string($subject) || '' === $subject) {
            throw new CustomUserMessageAuthenticationException('oidc.error.idp');
        }

        return new SelfValidatingPassport(new UserBadge($subject, fn (): OidcUserInterface => $this->resolveUser($subject, $claims)));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        return new RedirectResponse($this->urls->generate($this->targetRoute));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        return new RedirectResponse($this->urls->generate($this->loginRoute));
    }

    public function redirectUri(): string
    {
        return $this->urls->generate(self::CALLBACK_ROUTE, [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function resolveUser(string $subject, array $claims): OidcUserInterface
    {
        $user = $this->users->findOneBySubject($subject);
        if (null !== $user) {
            return $user;
        }

        $email = $claims['email'] ?? null;
        if (\is_string($email) && true === ($claims['email_verified'] ?? false)) {
            $user = $this->users->findOneByEmail($email);
            // ---- jamais de rattachement d'un compte déjà lié à un autre sub
            if ($user instanceof OidcUserInterface && null === $user->getOidcSubject()) {
                $user->setOidcSubject($subject);
                $this->users->flush();

                return $user;
            }
        }

        throw new CustomUserMessageAuthenticationException('oidc.error.unknown_user');
    }
}
