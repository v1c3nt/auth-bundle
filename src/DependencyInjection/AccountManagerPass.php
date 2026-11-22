<?php

namespace Vvbx\SuiteBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Vvbx\SuiteBundle\Account\AccountManagerInterface;

/**
 * Relie AccountManagerInterface à l'unique service de l'app qui l'implémente (repéré par autoconfiguration).
 */
final class AccountManagerPass implements CompilerPassInterface
{
    public const TAG = 'vvbx_suite.account_manager';

    public function process(ContainerBuilder $container): void
    {
        if ($container->has(AccountManagerInterface::class)) {
            return;
        }

        $ids = array_keys($container->findTaggedServiceIds(self::TAG));
        if (1 !== \count($ids)) {
            throw new LogicException(sprintf('vvbx/suite-bundle attend exactement un service qui implémente %s (trouvés : %s). Déclarez-en un ou un alias explicite.', AccountManagerInterface::class, [] === $ids ? 'aucun' : implode(', ', $ids)));
        }

        $container->setAlias(AccountManagerInterface::class, $ids[0]);
    }
}
