<?php

namespace Vvbx\SuiteBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Relie une interface à l'unique service qui l'implémente (repéré par autoconfiguration, tag = nom de l'interface),
 * seulement si le service qui en a besoin est déclaré ; un alias explicite de l'app l'emporte.
 */
final class SingleImplementationPass implements CompilerPassInterface
{
    /**
     * @param class-string $interface
     */
    public function __construct(
        private readonly string $interface,
        private readonly string $requiredBy,
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        if ($container->has($this->interface) || !$container->hasDefinition($this->requiredBy)) {
            return;
        }

        $ids = array_keys($container->findTaggedServiceIds($this->interface));
        if (1 !== \count($ids)) {
            throw new LogicException(sprintf('vvbx/suite-bundle attend exactement un service qui implémente %s (trouvés : %s). Déclarez-en un ou un alias explicite.', $this->interface, [] === $ids ? 'aucun' : implode(', ', $ids)));
        }

        $container->setAlias($this->interface, $ids[0]);
    }
}
