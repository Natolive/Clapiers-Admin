<?php

namespace App\Application\UseCase\SalleClosure\CreateSalleClosure;

use App\Common\Command\CommandInterface;
use DateTimeImmutable;

class CreateSalleClosureCommand implements CommandInterface
{
    public function __construct(
        public readonly DateTimeImmutable $startDate,
        public readonly DateTimeImmutable $endDate,
        public readonly ?string           $reason = null,
    ) {
    }
}
