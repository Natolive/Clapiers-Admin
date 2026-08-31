<?php

namespace App\Application\UseCase\License\GetLicenseReview;

use App\Common\Command\CommandInterface;

class GetLicenseReviewCommand implements CommandInterface
{
    public function __construct(
        public readonly int $id,
    ) {
    }
}
