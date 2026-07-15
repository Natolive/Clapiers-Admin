<?php

namespace App\Application\UseCase\Member\Media\RenameNode;

use Symfony\Component\Validator\Constraints as Assert;

class RenameNodePayload
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $name,
    ) {
    }
}
