<?php

namespace App\Application\UseCase\Setting\SetCurrentSeason;

use App\Common\Command\CommandInterface;
use App\Validator\Season;
use Symfony\Component\Validator\Constraints as Assert;

class SetCurrentSeasonCommand implements CommandInterface
{
    public function __construct(
        #[Assert\NotBlank]
        #[Season]
        public readonly string $season,
    ) {
    }
}
