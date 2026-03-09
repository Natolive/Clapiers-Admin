<?php

namespace App\Application\UseCase\Game\DeleteGame;

use App\Common\Command\CommandInterface;
use App\Entity\AppUser;

class DeleteGameCommand implements CommandInterface
{
    public function __construct(
        public readonly AppUser $user,
        public readonly int     $id,
    ) {
    }
}
