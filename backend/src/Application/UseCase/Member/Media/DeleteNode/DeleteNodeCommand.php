<?php

namespace App\Application\UseCase\Member\Media\DeleteNode;

use App\Common\Command\CommandInterface;

class DeleteNodeCommand implements CommandInterface
{
    public function __construct(
        public readonly int $memberId,
        public readonly string $nodeId,
    ) {
    }
}
