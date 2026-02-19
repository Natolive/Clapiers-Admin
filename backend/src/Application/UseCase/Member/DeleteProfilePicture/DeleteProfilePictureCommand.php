<?php

namespace App\Application\UseCase\Member\DeleteProfilePicture;

use App\Common\Command\CommandInterface;

class DeleteProfilePictureCommand implements CommandInterface
{
    public function __construct(
        public readonly int $memberId
    ) {
    }
}
