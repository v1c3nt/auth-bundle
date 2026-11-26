<?php

namespace Vvbx\SuiteBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Vvbx\SuiteBundle\Account\AccountManagerInterface;
use Vvbx\SuiteBundle\DependencyInjection\SingleImplementationPass;

final class SingleImplementationPassTest extends TestCase
{
    public function testSingleImplementationIsAliased(): void
    {
        $container = $this->container();
        $container->register('app.accounts', \stdClass::class)->addTag(AccountManagerInterface::class);

        $this->pass()->process($container);

        self::assertSame('app.accounts', (string) $container->getAlias(AccountManagerInterface::class));
    }

    public function testMissingImplementationIsExplained(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('trouvés : aucun');

        $this->pass()->process($this->container());
    }

    public function testNothingRequiredWhenTheConsumerIsNotDeclared(): void
    {
        $container = new ContainerBuilder();

        $this->pass()->process($container);

        self::assertFalse($container->has(AccountManagerInterface::class));
    }

    public function testExplicitAliasWins(): void
    {
        $container = $this->container();
        $container->register('app.a', \stdClass::class)->addTag(AccountManagerInterface::class);
        $container->register('app.b', \stdClass::class)->addTag(AccountManagerInterface::class);
        $container->setAlias(AccountManagerInterface::class, 'app.b');

        $this->pass()->process($container);

        self::assertSame('app.b', (string) $container->getAlias(AccountManagerInterface::class));
    }

    private function pass(): SingleImplementationPass
    {
        return new SingleImplementationPass(AccountManagerInterface::class, 'consumer');
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('consumer', \stdClass::class);

        return $container;
    }
}
