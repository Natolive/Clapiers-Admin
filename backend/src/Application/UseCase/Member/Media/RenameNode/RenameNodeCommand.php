<?php

namespace App\Application\UseCase\Member\Media\RenameNode;

use App\Common\Command\CommandInterface;

class RenameNodeCommand implements CommandInterface
{
    public function __construct(
        public readonly int $memberId,
        public readonly string $nodeId,
        public readonly string $name,
    ) {
    }
}
