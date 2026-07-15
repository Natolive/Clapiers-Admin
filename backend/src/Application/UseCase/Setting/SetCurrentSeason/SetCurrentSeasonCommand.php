<?php

namespace App\Application\UseCase\Setting\SetCurrentSeason;

use App\Common\Command\CommandInterface;
use Symfony\Component\Validator\Constraints as Assert;

class SetCurrentSeasonCommand implements CommandInterface
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{4}-\d{4}$/', message: 'Format attendu : AAAA-AAAA.')]
        public readonly string $season,
    ) {
    }
}
