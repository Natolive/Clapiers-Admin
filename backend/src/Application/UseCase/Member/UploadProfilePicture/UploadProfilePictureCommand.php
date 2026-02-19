<?php

namespace App\Application\UseCase\Member\UploadProfilePicture;

use App\Common\Command\CommandInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadProfilePictureCommand implements CommandInterface
{
    public function __construct(
        public readonly int $memberId,
        public readonly UploadedFile $file
    ) {
    }
}
