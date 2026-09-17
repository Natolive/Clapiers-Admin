<?php

namespace App\Controller\Input;

use App\Validator\Season;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corps de `PUT /api/member/{id}/fsgt` : le membre vient de l'URL, d'où le DTO
 * d'entrée séparé de la commande (cf. docs/architecture.md).
 */
class SetFsgtRegistrationInput
{
    public function __construct(
        public readonly bool $registered = false,
        #[Assert\Length(max: 50)]
        public readonly ?string $licenseNumber = null,
        #[Season]
        public readonly ?string $season = null,
    ) {
    }
}
