<?php

namespace App\Application\UseCase\License\UploadLicenseRequestDocument;

use App\Common\Command\CommandInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadLicenseRequestDocumentCommand implements CommandInterface
{
    public function __construct(
        public readonly string $token,
        public readonly string $systemKey,
        public readonly UploadedFile $file,
    ) {
    }
}
