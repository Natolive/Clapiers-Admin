<?php

namespace App\Application\UseCase\Team\CountTeamsBySeason;

use App\Common\Command\CommandInterface;

class CountTeamsBySeasonCommand implements CommandInterface
{
    public function __construct(
        public readonly int $seasonId
    ) {
    }
}
