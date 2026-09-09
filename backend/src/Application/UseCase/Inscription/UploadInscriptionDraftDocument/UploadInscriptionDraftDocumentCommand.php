<?php

namespace App\Application\UseCase\Inscription\UploadInscriptionDraftDocument;

use App\Common\Command\CommandInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadInscriptionDraftDocumentCommand implements CommandInterface
{
    public function __construct(
        public readonly string $token,
        public readonly string $systemKey,
        public readonly UploadedFile $file,
    ) {
    }
}
