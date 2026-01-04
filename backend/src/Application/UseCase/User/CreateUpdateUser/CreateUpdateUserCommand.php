<?php

namespace App\Application\UseCase\User\CreateUpdateUser;

use App\Common\Command\CommandInterface;
use Symfony\Component\Validator\Constraints as Assert;

class CreateUpdateUserCommand implements CommandInterface
{
    public function __construct(
        #[Assert\Email]
        public readonly string $email,
        public readonly string $role,
        public readonly ?string $password = null,
        public readonly ?int $id = null,
    ) {
    }
}
