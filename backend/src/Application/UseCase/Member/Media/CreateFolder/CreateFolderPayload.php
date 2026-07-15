<?php

namespace App\Application\UseCase\Member\Media\CreateFolder;

use Symfony\Component\Validator\Constraints as Assert;

class CreateFolderPayload
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $name,
        public readonly ?string $parentId = null,
    ) {
    }
}
