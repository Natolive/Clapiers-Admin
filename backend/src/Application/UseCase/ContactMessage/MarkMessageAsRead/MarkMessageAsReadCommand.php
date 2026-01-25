<?php

namespace App\Application\UseCase\ContactMessage\MarkMessageAsRead;

use App\Common\Command\CommandInterface;
use Symfony\Component\Validator\Constraints as Assert;

class MarkMessageAsReadCommand implements CommandInterface
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        public readonly int $messageId,
    ) {
    }
}
