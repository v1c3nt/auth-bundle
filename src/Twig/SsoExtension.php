<?php

namespace Vvbx\AuthBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Vvbx\AuthBundle\Oidc\OidcClient;

// ---- {% if vvbx_auth_sso_enabled() %} : afficher le bouton de connexion SSO (path('vvbx_auth_oidc_login'))
final class SsoExtension extends AbstractExtension
{
    public function __construct(private readonly OidcClient $oidc)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('vvbx_auth_sso_enabled', $this->oidc->isEnabled(...))];
    }
}
