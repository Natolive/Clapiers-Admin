<?php

namespace App\Application\UseCase\Member\SetFsgtRegistration;

use App\Common\Command\CommandInterface;

class SetFsgtRegistrationCommand implements CommandInterface
{
    public function __construct(
        public readonly int $memberId,
        public readonly bool $registered,
        public readonly ?string $licenseNumber = null,
        public readonly ?string $season = null,
    ) {
    }
}
