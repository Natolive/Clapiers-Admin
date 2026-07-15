<?php

namespace App\Application\UseCase\Member\Media\UploadDocumentFile;

use App\Common\Command\CommandInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadDocumentFileCommand implements CommandInterface
{
    public function __construct(
        public readonly int $memberId,
        public readonly string $nodeId,
        public readonly UploadedFile $file,
    ) {
    }
}
