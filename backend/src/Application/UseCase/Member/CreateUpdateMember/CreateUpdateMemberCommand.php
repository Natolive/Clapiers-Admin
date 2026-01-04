<?php

namespace App\Application\UseCase\Member\CreateUpdateMember;

use App\Common\Command\CommandInterface;
use Symfony\Component\Validator\Constraints as Assert;

class CreateUpdateMemberCommand implements CommandInterface
{
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $phoneNumber,

        #[Assert\Email]
        public readonly string $email,
        public readonly int $teamId,
        public readonly ?int $id = null,
    ) {
    }
}
