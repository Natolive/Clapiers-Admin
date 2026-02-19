<?php

namespace App\Application\UseCase\User\LinkMember;

use App\Common\Command\CommandInterface;

class LinkMemberCommand implements CommandInterface
{
    public function __construct(
        public readonly int $userId,
        public readonly int $memberId,
    ) {
    }
}
