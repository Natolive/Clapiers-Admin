<?php

namespace App\Application\UseCase\Member\CreateUpdateMember;

use App\Common\Command\CommandInterface;

class CreateUpdateMemberCommand implements CommandInterface
{
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly int $teamId,
        public readonly ?int $id = null,
    ) {
    }
}
