<?php

namespace App\Application\UseCase\Member\CountMembersBySeason;

use App\Common\Command\CommandInterface;

class CountMembersBySeasonCommand implements CommandInterface
{
    public function __construct(
        public readonly int $seasonId
    ) {
    }
}
