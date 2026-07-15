<?php

namespace App\Application\UseCase\Member\Media\GetMemberMedia;

use App\Common\Command\CommandInterface;

class GetMemberMediaCommand implements CommandInterface
{
    public function __construct(
        public readonly int $memberId,
    ) {
    }
}
