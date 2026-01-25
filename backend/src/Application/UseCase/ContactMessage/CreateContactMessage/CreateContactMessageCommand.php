<?php

namespace App\Application\UseCase\ContactMessage\CreateContactMessage;

use App\Common\Command\CommandInterface;
use Symfony\Component\Validator\Constraints as Assert;

class CreateContactMessageCommand implements CommandInterface
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $firstName,

        #[Assert\NotBlank]
        public readonly string $lastName,

        #[Assert\NotBlank]
        #[Assert\Email]
        public readonly string $email,

        #[Assert\NotBlank]
        public readonly string $subject,

        #[Assert\NotBlank]
        public readonly string $message,
    ) {
    }
}
