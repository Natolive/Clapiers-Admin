<?php

namespace App\Application\UseCase\Team\CreateUpdateTeam;

use App\Common\Command\CommandInterface;

class CreateUpdateTeamCommand implements CommandInterface
{
    public function __construct(
        public readonly string $name,
        public readonly ?int $id = null,
    ) {
    }
}
