<?php

namespace App\Application\UseCase\User\AcceptInvitation;

use App\Common\Command\CommandInterface;

class AcceptInvitationCommand implements CommandInterface
{
    public function __construct(
        public readonly string $token,
        public readonly string $password,
    ) {
    }
}
