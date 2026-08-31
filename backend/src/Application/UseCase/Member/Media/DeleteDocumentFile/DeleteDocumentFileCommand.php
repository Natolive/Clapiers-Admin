<?php

namespace App\Application\UseCase\Member\Media\DeleteDocumentFile;

use App\Common\Command\CommandInterface;

class DeleteDocumentFileCommand implements CommandInterface
{
    public function __construct(
        public readonly int $memberId,
        public readonly string $nodeId,
    ) {
    }
}
