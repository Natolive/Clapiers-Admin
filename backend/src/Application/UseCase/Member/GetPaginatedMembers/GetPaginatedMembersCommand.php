<?php

namespace App\Application\UseCase\Member\GetPaginatedMembers;

use App\Common\Command\CommandInterface;

class GetPaginatedMembersCommand implements CommandInterface
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $limit = 10,
        public readonly string $sortField = 'firstName',
        public readonly string $sortOrder = 'asc',
        public readonly ?string $search = null,
        public readonly ?int $teamId = null,
        public readonly ?bool $licensePaid = null,
        public readonly ?bool $hasLicense = null,
    ) {
    }
}
