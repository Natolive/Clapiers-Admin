<?php

namespace App\Application\UseCase\User\GetInvitation;

use App\Common\Command\CommandInterface;

class GetInvitationCommand implements CommandInterface
{
    public function __construct(
        public readonly string $token,
    ) {
    }
}
