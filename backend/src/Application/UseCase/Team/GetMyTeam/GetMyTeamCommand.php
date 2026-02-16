<?php

namespace App\Application\UseCase\Team\GetMyTeam;

use App\Common\Command\CommandInterface;
use App\Entity\AppUser;

class GetMyTeamCommand implements CommandInterface
{
    public function __construct(
        public readonly AppUser $user
    ) {
    }
}
