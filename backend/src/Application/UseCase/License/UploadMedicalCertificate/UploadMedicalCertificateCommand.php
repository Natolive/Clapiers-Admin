<?php

namespace App\Application\UseCase\License\UploadMedicalCertificate;

use App\Common\Command\CommandInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadMedicalCertificateCommand implements CommandInterface
{
    public function __construct(
        public readonly string $token,
        public readonly UploadedFile $file,
    ) {
    }
}
