<?php

namespace App\Application\UseCase\Log\GetPaginatedLogs;

use App\Common\Command\CommandInterface;
use Symfony\Component\Validator\Constraints as Assert;

class GetPaginatedLogsCommand implements CommandInterface
{
    public function __construct(
        #[Assert\Positive]
        public readonly int $page = 1,
        #[Assert\Positive]
        public readonly int $limit = 50,
        public readonly ?string $level = null,
        public readonly ?string $search = null,
    ) {
    }
}
