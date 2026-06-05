<?php

namespace App\Application\UseCase\ContactMessage\GetPaginatedContactMessages;

use App\Common\Command\CommandInterface;

class GetPaginatedContactMessagesCommand implements CommandInterface
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $limit = 20,
        public readonly ?string $search = null,
    ) {
    }
}
