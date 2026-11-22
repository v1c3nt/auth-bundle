<?php

namespace Vvbx\SuiteBundle\Security;

use Doctrine\ORM\Mapping as ORM;

/**
 * Colonne du sub pour une entité qui implémente OidcUserInterface.
 *
 * L'unicité reste déclarée sur l'entité : #[ORM\UniqueConstraint(name: 'UNIQ_OIDC_SUBJECT', fields: ['oidcSubject'])].
 */
trait OidcSubjectTrait
{
    // ---- identifiant (sub) chez l'IdP de la suite, fixé à la première connexion SSO
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $oidcSubject = null;

    public function getOidcSubject(): ?string
    {
        return $this->oidcSubject;
    }

    public function setOidcSubject(?string $oidcSubject): static
    {
        $this->oidcSubject = $oidcSubject;

        return $this;
    }
}
