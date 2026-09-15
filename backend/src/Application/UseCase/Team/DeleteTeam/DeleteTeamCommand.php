<?php

namespace App\Application\UseCase\Team\DeleteTeam;

use App\Common\Command\CommandInterface;

class DeleteTeamCommand implements CommandInterface
{
    public function __construct(
        public readonly int $id,
    ) {
    }
}
