<?php

namespace App\Controller\Input;

use Symfony\Component\Validator\Constraints as Assert;

class GetGamesIcsInput
{
    public function __construct(
        public readonly ?int $teamId = null,

        /** Rappel en minutes avant le match (VALARM), 7 jours max */
        #[Assert\Range(min: 1, max: 10080)]
        public readonly ?int $alarm = null,
    ) {
    }
}
