<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\RegexValidator;

/**
 * Valide le format d'une saison sportive : "AAAA-AAAA" (ex. 2026-2027).
 *
 * Générique : à poser sur tout champ `season` d'une commande / DTO / payload
 * mappé via MapQueryString ou MapRequestPayload. null et chaîne vide passent
 * (champ optionnel) ; combiner avec #[Assert\NotBlank] si la saison est requise.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
class Season extends Regex
{
    public function __construct()
    {
        parent::__construct(
            pattern: '/^\d{4}-\d{4}$/',
            message: 'Format attendu : AAAA-AAAA.',
        );
    }

    // Réutilise le validateur natif de Regex (sinon Symfony cherche SeasonValidator).
    public function validatedBy(): string
    {
        return RegexValidator::class;
    }
}
