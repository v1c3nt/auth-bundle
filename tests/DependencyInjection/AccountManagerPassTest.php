<?php

namespace Vvbx\SuiteBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Vvbx\SuiteBundle\Account\AccountManagerInterface;
use Vvbx\SuiteBundle\DependencyInjection\AccountManagerPass;

final class AccountManagerPassTest extends TestCase
{
    public function testSingleImplementationIsAliased(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.accounts', \stdClass::class)->addTag(AccountManagerPass::TAG);

        (new AccountManagerPass())->process($container);

        self::assertSame('app.accounts', (string) $container->getAlias(AccountManagerInterface::class));
    }

    public function testMissingImplementationIsExplained(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('trouvés : aucun');

        (new AccountManagerPass())->process(new ContainerBuilder());
    }

    public function testExplicitAliasWins(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.a', \stdClass::class)->addTag(AccountManagerPass::TAG);
        $container->register('app.b', \stdClass::class)->addTag(AccountManagerPass::TAG);
        $container->setAlias(AccountManagerInterface::class, 'app.b');

        (new AccountManagerPass())->process($container);

        self::assertSame('app.b', (string) $container->getAlias(AccountManagerInterface::class));
    }
}
