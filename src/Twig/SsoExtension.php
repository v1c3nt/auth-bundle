<?php

namespace Vvbx\SuiteBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Vvbx\SuiteBundle\Oidc\OidcClient;

// ---- {% if vvbx_sso_enabled() %} : afficher le bouton de connexion SSO (path('vvbx_suite_oidc_login'))
final class SsoExtension extends AbstractExtension
{
    public function __construct(private readonly OidcClient $oidc)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('vvbx_sso_enabled', $this->oidc->isEnabled(...))];
    }
}
