<?php

namespace App\Application\UseCase\Game\CreateUpdateGame;

use App\Common\Command\CommandInterface;
use App\Entity\AppUser;
use App\Entity\Enum\GameVenue;

class CreateUpdateGameCommand implements CommandInterface
{
    public function __construct(
        public readonly AppUser   $user,
        public readonly string    $opponent,
        public readonly string    $date,
        public readonly ?string   $meetingTime = null,
        public readonly GameVenue $venue = GameVenue::HOME,
        public readonly ?string   $location = null,
        public readonly ?int      $teamId = null,
        public readonly ?int      $id = null,
    ) {
    }
}
