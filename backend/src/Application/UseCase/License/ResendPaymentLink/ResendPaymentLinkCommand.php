<?php

namespace App\Application\UseCase\License\ResendPaymentLink;

use App\Common\Command\CommandInterface;

class ResendPaymentLinkCommand implements CommandInterface
{
    public function __construct(
        public readonly int $id,
    ) {
    }
}
