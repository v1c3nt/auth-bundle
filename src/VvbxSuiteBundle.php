<?php

namespace Vvbx\SuiteBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Vvbx\SuiteBundle\Account\AccountManagerInterface;
use Vvbx\SuiteBundle\Command\PreauthorizeUserCommand;
use Vvbx\SuiteBundle\Controller\OidcController;
use Vvbx\SuiteBundle\DependencyInjection\AccountManagerPass;
use Vvbx\SuiteBundle\Doctrine\UserRepository;
use Vvbx\SuiteBundle\Oidc\OidcClient;
use Vvbx\SuiteBundle\Security\OidcAuthenticator;
use Vvbx\SuiteBundle\Security\UserChecker;
use Vvbx\SuiteBundle\Security\UserProvider;
use Vvbx\SuiteBundle\Twig\SsoExtension;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Briques communes des apps de la suite. Aujourd'hui : connexion SSO OIDC (code + PKCE) et comptes désactivables.
 *
 * Le bundle ne fournit pas l'entité User : l'app garde la sienne et implémente OidcUserInterface
 * (et ActivatableUserInterface), ainsi que AccountManagerInterface pour la pré-autorisation des comptes.
 */
final class VvbxSuiteBundle extends AbstractBundle
{
    protected string $extensionAlias = 'vvbx_suite';

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
