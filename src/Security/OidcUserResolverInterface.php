<?php

namespace Vvbx\SuiteBundle\Security;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Trouve l'utilisateur d'une connexion SSO réussie à partir du sub et des claims lus sur userinfo.
 *
 * Fourni par le bundle (AccountUserResolver) quand l'app déclare vvbx_suite.user_class ; une app sans comptes
 * locaux (ex. le portail client) fournit le sien.
 */
interface OidcUserResolverInterface
{
    /**
     * @param array<string, mixed> $claims
     *
     * @throws CustomUserMessageAuthenticationException refus affiché sur la page de connexion (clé de traduction)
     */
    public function resolve(string $subject, array $claims): UserInterface;
}
