<?php

namespace App\Application\UseCase\User\GetPaginatedUsers;

use App\Common\Command\CommandInterface;

class GetPaginatedUsersCommand implements CommandInterface
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $limit = 10,
        public readonly string $sortField = 'email',
        public readonly string $sortOrder = 'asc',
        public readonly ?string $search = null,
    ) {
    }
}
