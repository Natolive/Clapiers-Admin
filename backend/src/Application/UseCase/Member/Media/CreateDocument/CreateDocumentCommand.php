<?php

namespace App\Application\UseCase\Member\Media\CreateDocument;

use App\Common\Command\CommandInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CreateDocumentCommand implements CommandInterface
{
    public function __construct(
        public readonly int $memberId,
        public readonly string $name,
        public readonly UploadedFile $file,
        public readonly ?string $parentId = null,
    ) {
    }
}
