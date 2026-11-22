<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Vvbx\SuiteBundle\Controller\OidcController;

// ---- à importer par l'app : vvbx_suite: { resource: '@VvbxSuiteBundle/config/routes.php' } ; garder /oidc/ en PUBLIC_ACCESS
return static function (RoutingConfigurator $routes): void {
    $routes->add('vvbx_suite_oidc_login', '/oidc/login')
        ->controller([OidcController::class, 'login'])
        ->methods(['GET']);

    $routes->add('vvbx_suite_oidc_callback', '/oidc/callback')
        ->controller([OidcController::class, 'callback'])
        ->methods(['GET']);
};
