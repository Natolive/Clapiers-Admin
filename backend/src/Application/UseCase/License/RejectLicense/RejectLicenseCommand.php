<?php

namespace App\Application\UseCase\License\RejectLicense;

use App\Common\Command\CommandInterface;

class RejectLicenseCommand implements CommandInterface
{
    public function __construct(
        public readonly int $id,
        public readonly string $reason,
    ) {
    }
}
