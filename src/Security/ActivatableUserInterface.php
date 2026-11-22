<?php

namespace Vvbx\SuiteBundle\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Compte désactivable : un compte inactif ne se connecte plus (mot de passe ni SSO)
 * et sa session tombe à la requête suivante. Il est conservé pour l'historique.
 */
interface ActivatableUserInterface extends UserInterface
{
    public function isActive(): bool;
}
