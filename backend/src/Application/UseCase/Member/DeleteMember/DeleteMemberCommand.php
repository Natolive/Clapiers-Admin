<?php

namespace App\Application\UseCase\Member\DeleteMember;

use App\Common\Command\CommandInterface;

class DeleteMemberCommand implements CommandInterface
{
    public function __construct(
        public readonly int $id,
    ) {
    }
}
