<?php

namespace App\Controller\Input;

use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

class CreateSalleClosureInput
{
    public function __construct(
        #[Assert\NotNull]
        public readonly ?DateTimeImmutable $startDate = null,

        #[Assert\NotNull]
        public readonly ?DateTimeImmutable $endDate = null,

        #[Assert\Length(max: 255)]
        public readonly ?string $reason = null,
    ) {
    }
}
