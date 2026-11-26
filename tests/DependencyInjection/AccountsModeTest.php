<?php

namespace Vvbx\SuiteBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Vvbx\SuiteBundle\Command\PreauthorizeUserCommand;
use Vvbx\SuiteBundle\Doctrine\UserRepository;
use Vvbx\SuiteBundle\Security\AccountUserResolver;
use Vvbx\SuiteBundle\Security\OidcAuthenticator;
use Vvbx\SuiteBundle\Security\UserProvider;
use Vvbx\SuiteBundle\VvbxSuiteBundle;

final class AccountsModeTest extends TestCase
{
    public function testAccountServicesWithUserClass(): void
    {
        $builder = $this->load(['user_class' => \stdClass::class]);

        foreach ([UserRepository::class, AccountUserResolver::class, UserProvider::class, PreauthorizeUserCommand::class] as $id) {
            self::assertTrue($builder->hasDefinition($id), $id);
        }
    }

    public function testNoAccountServicesWithoutUserClass(): void
    {
        $builder = $this->load([]);

        self::assertTrue($builder->hasDefinition(OidcAuthenticator::class));
        foreach ([UserRepository::class, AccountUserResolver::class, UserProvider::class, PreauthorizeUserCommand::class] as $id) {
            self::assertFalse($builder->hasDefinition($id), $id);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $builder = new ContainerBuilder();
        // ---- paramètres posés par le noyau, lus par BundleExtension
        $builder->setParameter('kernel.environment', 'test');
        $builder->setParameter('kernel.build_dir', sys_get_temp_dir());
        $extension = (new VvbxSuiteBundle())->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load([$config], $builder);

        return $builder;
    }
}
