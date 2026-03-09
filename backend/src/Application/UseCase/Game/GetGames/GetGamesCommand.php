<?php

namespace App\Application\UseCase\Game\GetGames;

use App\Common\Command\CommandInterface;
use App\Entity\AppUser;

class GetGamesCommand implements CommandInterface
{
    public function __construct(
        public readonly AppUser $user,
        public readonly ?int    $teamId = null,
        public readonly ?string $start = null,
        public readonly ?string $end = null,
    ) {
    }
}
