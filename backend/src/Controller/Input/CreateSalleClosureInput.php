<?php

namespace App\Controller\Input;

use Symfony\Component\Validator\Constraints as Assert;

class CreateSalleClosureInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date]
        public readonly string  $startDate = '',

        #[Assert\NotBlank]
        #[Assert\Date]
        public readonly string  $endDate = '',

        #[Assert\Length(max: 255)]
        public readonly ?string $reason = null,
    ) {
    }
}
