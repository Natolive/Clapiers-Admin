<?php

namespace App\Application\UseCase\Member\Media\CreateFolder;

use App\Common\Command\CommandInterface;

class CreateFolderCommand implements CommandInterface
{
    public function __construct(
        public readonly int $memberId,
        public readonly string $name,
        public readonly ?string $parentId = null,
    ) {
    }
}
