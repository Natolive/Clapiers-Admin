<?php

namespace App\Application\UseCase\License\ApproveLicense;

use App\Common\Command\CommandInterface;

class ApproveLicenseCommand implements CommandInterface
{
    public function __construct(
        public readonly int $id,
        public readonly int $helloAssoTierId,
        public readonly int $amount,
    ) {
    }
}
