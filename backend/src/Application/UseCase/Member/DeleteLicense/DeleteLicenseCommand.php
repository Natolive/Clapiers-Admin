<?php

namespace App\Application\UseCase\Member\DeleteLicense;

use App\Common\Command\CommandInterface;

class DeleteLicenseCommand implements CommandInterface
{
    public function __construct(
        public readonly int $memberId
    ) {
    }
}
