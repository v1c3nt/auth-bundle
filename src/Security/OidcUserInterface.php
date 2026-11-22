<?php

namespace Vvbx\SuiteBundle\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Compte de l'app connectable par le SSO : rattaché à l'identifiant (sub) de l'IdP, jamais à l'e-mail.
 *
 * L'entité déclare elle-même la colonne (voir OidcSubjectTrait) et sa contrainte d'unicité.
 */
interface OidcUserInterface extends UserInterface
{
    public function getOidcSubject(): ?string;

    public function setOidcSubject(?string $oidcSubject): static;
}
