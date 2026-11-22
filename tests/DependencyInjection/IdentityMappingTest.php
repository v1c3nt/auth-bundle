<?php

namespace Vvbx\SuiteBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Vvbx\SuiteBundle\VvbxSuiteBundle;

final class IdentityMappingTest extends TestCase
{
    public function testMappingIsPrependedWhenDoctrineIsPresent(): void
    {
        $builder = new ContainerBuilder();
        $builder->registerExtension(new class extends Extension {
            /**
             * @param array<mixed> $configs
             */
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'doctrine';
            }
        });

        $this->prepend($builder);

        $mapping = $builder->getExtensionConfig('doctrine')[0]['orm']['mappings']['VvbxSuiteIdentity'] ?? null;
        self::assertIsArray($mapping);
        self::assertSame('Vvbx\SuiteBundle\Identity', $mapping['prefix']);
        self::assertDirectoryExists($mapping['dir']);
    }

    public function testNothingIsPrependedWithoutDoctrine(): void
    {
        $builder = new ContainerBuilder();

        $this->prepend($builder);

        self::assertSame([], $builder->getExtensionConfig('doctrine'));
    }

    private function prepend(ContainerBuilder $builder): void
    {
        // ---- paramètres posés par le noyau, lus par BundleExtension
        $builder->setParameter('kernel.environment', 'test');
        $builder->setParameter('kernel.build_dir', sys_get_temp_dir());
        $extension = (new VvbxSuiteBundle())->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($builder);
    }
}
