<?php

namespace App\Application\UseCase\Team\DownloadMyTeamMemberPhoto;

use App\Common\Command\CommandInterface;
use App\Entity\AppUser;

class DownloadMyTeamMemberPhotoCommand implements CommandInterface
{
    public function __construct(
        public readonly AppUser $user,
        public readonly int $memberId
    ) {
    }
}
