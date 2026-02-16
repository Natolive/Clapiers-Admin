<?php

namespace App\Application\UseCase\Member\UploadLicense;

use App\Common\Command\CommandInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadLicenseCommand implements CommandInterface
{
    public function __construct(
        public readonly int $memberId,
        public readonly UploadedFile $file
    ) {
    }
}
