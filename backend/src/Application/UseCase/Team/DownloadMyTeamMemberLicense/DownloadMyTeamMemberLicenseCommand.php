<?php

namespace App\Application\UseCase\Team\DownloadMyTeamMemberLicense;

use App\Common\Command\CommandInterface;
use App\Entity\AppUser;

class DownloadMyTeamMemberLicenseCommand implements CommandInterface
{
    public function __construct(
        public readonly AppUser $user,
        public readonly int $memberId
    ) {
    }
}
