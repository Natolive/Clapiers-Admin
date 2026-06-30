<?php

namespace App\Application\UseCase\License\RejectLicense;

use Symfony\Component\Validator\Constraints as Assert;

class RejectLicensePayload
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 1000)]
        public readonly string $reason,
    ) {
    }
}
