<?php

namespace App\Application\UseCase\SalleClosure\CreateSalleClosure;

use App\Common\Command\CommandInterface;

class CreateSalleClosureCommand implements CommandInterface
{
    public function __construct(
        public readonly string  $startDate,
        public readonly string  $endDate,
        public readonly ?string $reason = null,
    ) {
    }
}
