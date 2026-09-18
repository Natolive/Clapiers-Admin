<?php

namespace App\Application\UseCase\Member\DownloadMemberPhoto;

use App\Common\Command\CommandInterface;
use App\Entity\AppUser;

class DownloadMemberPhotoCommand implements CommandInterface
{
    public function __construct(
        public readonly AppUser $user,
        public readonly int $memberId
    ) {
    }
}
