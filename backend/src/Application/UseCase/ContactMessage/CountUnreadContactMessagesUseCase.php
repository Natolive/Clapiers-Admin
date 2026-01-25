<?php

namespace App\Application\UseCase\ContactMessage;

use App\Common\Command\CommandInterface;
use App\Common\UseCase\AbstractUseCase;
use App\Repository\ContactMessageRepository;

class CountUnreadContactMessagesUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly ContactMessageRepository $contactMessageRepository
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        return ['count' => $this->contactMessageRepository->countUnread()];
    }
}
