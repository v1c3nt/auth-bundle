<?php

namespace Vvbx\AuthBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Vvbx\AuthBundle\Account\AccountManagerInterface;
use Vvbx\AuthBundle\Command\PreauthorizeUserCommand;
use Vvbx\AuthBundle\Controller\OidcController;
use Vvbx\AuthBundle\DependencyInjection\AccountManagerPass;
use Vvbx\AuthBundle\Doctrine\UserRepository;
use Vvbx\AuthBundle\Oidc\OidcClient;
use Vvbx\AuthBundle\Security\OidcAuthenticator;
use Vvbx\AuthBundle\Security\UserChecker;
use Vvbx\AuthBundle\Security\UserProvider;
use Vvbx\AuthBundle\Twig\SsoExtension;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Connexion SSO OIDC (code + PKCE) et comptes désactivables pour les apps de la suite.
 *
 * Le bundle ne fournit pas l'entité User : l'app garde la sienne et implémente OidcUserInterface
 * (et ActivatableUserInterface), ainsi que AccountManagerInterface pour la pré-autorisation des comptes.
 */
final class VvbxAuthBundle extends AbstractBundle
{
    protected string $extensionAlias = 'vvbx_auth';

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new AccountManagerPass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('user_class')
                    ->info('Entité User de l\'app (implémente OidcUserInterface)')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('email_field')
                    ->info('Propriété de l\'entité qui porte l\'e-mail (identifiant de connexion)')
                    ->defaultValue('email')
                ->end()
                ->scalarNode('target_route')
                    ->info('Route vers laquelle rediriger après une connexion SSO réussie')
                    ->defaultValue('admin')
                ->end()
                ->scalarNode('login_route')
                    ->info('Route de la page de connexion, où les erreurs SSO sont affichées')
                    ->defaultValue('app_login')
                ->end()
            ->end();
    }

    /**
     * @param array{user_class: class-string, email_field: string, target_route: string, login_route: string} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services()->defaults()->autowire()->autoconfigure();

        // ---- l'implémentation de AccountManagerInterface fournie par l'app est reliée par AccountManagerPass
        $builder->registerForAutoconfiguration(AccountManagerInterface::class)->addTag(AccountManagerPass::TAG);

        $services->set(OidcClient::class);

        $services->set(UserRepository::class)
            ->arg('$userClass', $config['user_class'])
            ->arg('$emailField', $config['email_field']);

        $services->set(OidcAuthenticator::class)
            ->arg('$targetRoute', $config['target_route'])
            ->arg('$loginRoute', $config['login_route']);

        $services->set(UserChecker::class);
        $services->set(UserProvider::class);

        $services->set(OidcController::class)->public()->tag('controller.service_arguments');

        // ---- délègue à l'implémentation de AccountManagerInterface fournie par l'app
        $services->set(PreauthorizeUserCommand::class)
            ->arg('$accounts', service(AccountManagerInterface::class));

        if (class_exists(\Twig\Extension\AbstractExtension::class)) {
            $services->set(SsoExtension::class);
        }
    }
}
