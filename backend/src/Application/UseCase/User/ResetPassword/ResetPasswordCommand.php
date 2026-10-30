<?php

namespace App\Application\UseCase\User\ResetPassword;

use App\Common\Command\CommandInterface;

class ResetPasswordCommand implements CommandInterface
{
    public function __construct(
        public readonly string $token,
        public readonly string $password,
    ) {
    }
}
