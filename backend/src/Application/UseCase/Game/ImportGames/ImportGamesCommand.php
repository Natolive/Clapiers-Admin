<?php

namespace App\Application\UseCase\Game\ImportGames;

use App\Common\Command\CommandInterface;
use App\Entity\AppUser;

readonly class ImportGamesCommand implements CommandInterface
{
    public function __construct(
        public AppUser $user,
        public string  $csvContent,
    ) {
    }
}
