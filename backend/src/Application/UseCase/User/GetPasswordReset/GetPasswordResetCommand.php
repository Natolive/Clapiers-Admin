<?php

namespace App\Application\UseCase\User\GetPasswordReset;

use App\Common\Command\CommandInterface;

class GetPasswordResetCommand implements CommandInterface
{
    public function __construct(
        public readonly string $token,
    ) {
    }
}
