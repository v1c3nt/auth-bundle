<?php

namespace Vvbx\SuiteBundle\Validator;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Compound;

/**
 * N° SIREN : seule définition du format (9 chiffres) et de la normalisation de saisie, pour toutes les apps de la suite.
 * La valeur nulle est acceptée : l'obligation de le saisir reste propre au contexte (ex. entreprise donatrice).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class Siren extends Compound
{
    public const MESSAGE = 'identity.siren.invalid';

    // ---- « 123 456 789 » saisi tel qu'imprimé est accepté ; chaîne vide ramenée à null
    public static function normalize(?string $siren): ?string
    {
        $siren = null === $siren ? null : trim((string) preg_replace('/\s+/u', '', $siren));

        return '' === $siren ? null : $siren;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<Assert\Regex>
     */
    protected function getConstraints(array $options): array
    {
        return [new Assert\Regex(pattern: '/^\d{9}$/', message: self::MESSAGE)];
    }
}
