<?php

namespace App\Application\UseCase\User\CreateUpdateUser;

use App\Common\Command\CommandInterface;

class CreateUpdateUserCommand implements CommandInterface
{
    public function __construct(
        public readonly string $email,
        public readonly string $role,
        public readonly ?string $password = null,
        public readonly ?int $id = null,
    ) {
    }
}
