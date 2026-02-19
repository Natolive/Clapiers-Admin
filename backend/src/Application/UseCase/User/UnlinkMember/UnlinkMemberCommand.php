<?php

namespace App\Application\UseCase\User\UnlinkMember;

use App\Common\Command\CommandInterface;

class UnlinkMemberCommand implements CommandInterface
{
    public function __construct(
        public readonly int $userId,
    ) {
    }
}
