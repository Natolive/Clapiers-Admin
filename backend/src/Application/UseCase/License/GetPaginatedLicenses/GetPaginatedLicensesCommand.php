<?php

namespace App\Application\UseCase\License\GetPaginatedLicenses;

use App\Common\Command\CommandInterface;
use Symfony\Component\Validator\Constraints as Assert;

class GetPaginatedLicensesCommand implements CommandInterface
{
    public function __construct(
        #[Assert\Positive]
        public readonly int $page = 1,
        #[Assert\Positive]
        public readonly int $limit = 20,
        public readonly ?string $status = null,
        public readonly ?string $search = null,
    ) {
    }
}
