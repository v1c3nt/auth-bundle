<?php

namespace Vvbx\SuiteBundle\Identity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Identité de la structure cliente (nom, adresse, contact, SIREN/RNA), de même forme dans toutes les apps de la suite.
 *
 * L'app l'intègre dans l'entité de son choix :
 *     #[ORM\Embedded(class: Identity::class, columnPrefix: false)]
 *     #[Assert\Valid]
 *     private Identity $identity;
 *
 * Colonnes nullables : une installation neuve démarre vide ; l'obligation est portée par la validation.
 * Les exigences propres à un module (ex. SIREN obligatoire pour délivrer des reçus fiscaux) restent dans l'app.
 */
#[ORM\Embeddable]
class Identity
{
    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private ?string $name = null;

    // ---- nom d'usage (en-têtes, e-mails)
    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $shortName = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    private ?string $acronym = null;

    // ---- numéro, voie, complément ; plusieurs lignes possibles
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $street = null;

    // ---- France seulement
    #[ORM\Column(length: 5, nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{5}$/', message: 'identity.postal_code.invalid')]
    private ?string $postalCode = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $city = null;

    // ---- contact public, imprimé sur les documents
    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    // ---- format libre : trop de variantes pour une regex
    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(requireTld: true)]
    #[Assert\Length(max: 255)]
    private ?string $website = null;

    #[ORM\Column(length: 9, nullable: true)]
    #[Assert\Regex(pattern: '/^\d{9}$/', message: 'identity.siren.invalid')]
    private ?string $siren = null;

    // ---- répertoire national des associations : W suivi de 9 chiffres
    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Regex(pattern: '/^W\d{9}$/', message: 'identity.rna.invalid')]
    private ?string $rna = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = self::clean($name);

        return $this;
    }

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(?string $shortName): static
    {
        $this->shortName = self::clean($shortName);

        return $this;
    }

    // ---- nom d'usage s'il existe, sinon nom officiel
    public function getDisplayName(): ?string
    {
        return $this->shortName ?? $this->name;
    }

    public function getAcronym(): ?string
    {
        return $this->acronym;
    }

    public function setAcronym(?string $acronym): static
    {
        $this->acronym = self::clean($acronym);

        return $this;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(?string $street): static
    {
        $this->street = self::clean($street);

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = self::compact($postalCode);

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = self::clean($city);

        return $this;
    }

    /**
     * Adresse postale sur plusieurs lignes (voie, puis code postal et ville), pour les documents.
     */
    public function getPostalAddress(): string
    {
        $locality = trim(($this->postalCode ?? '').' '.($this->city ?? ''));

        return implode("\n", array_filter([$this->street, $locality], static fn (?string $line): bool => null !== $line && '' !== $line));
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = self::clean($email);

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = self::clean($phone);

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = self::clean($website);

        return $this;
    }

    public function getSiren(): ?string
    {
        return $this->siren;
    }

    // ---- « 123 456 789 » saisi tel qu'imprimé est accepté
    public function setSiren(?string $siren): static
    {
        $this->siren = self::compact($siren);

        return $this;
    }

    public function getRna(): ?string
    {
        return $this->rna;
    }

    public function setRna(?string $rna): static
    {
        $rna = self::compact($rna);
        $this->rna = null === $rna ? null : strtoupper($rna);

        return $this;
    }

    // ---- espaces de bord retirés, chaîne vide ramenée à null
    private static function clean(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);

        return '' === $value ? null : $value;
    }

    // ---- tous les espaces retirés (numéros saisis par groupes)
    private static function compact(?string $value): ?string
    {
        return self::clean(null === $value ? null : (string) preg_replace('/\s+/u', '', $value));
    }
}
