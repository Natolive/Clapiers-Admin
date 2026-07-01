<?php

namespace App\Application\UseCase\License\GetLicenseForPayment;

use App\Common\Command\CommandInterface;

class GetLicenseForPaymentCommand implements CommandInterface
{
    public function __construct(
        public readonly string $token,
    ) {
    }
}
