<?php

namespace App\Application\UseCase\License\CreateCheckout;

use App\Common\Command\CommandInterface;

class CreateCheckoutCommand implements CommandInterface
{
    public function __construct(
        public readonly string $token,
    ) {
    }
}
