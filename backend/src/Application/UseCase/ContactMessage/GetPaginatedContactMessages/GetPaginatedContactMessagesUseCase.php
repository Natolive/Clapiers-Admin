<?php

namespace App\Application\UseCase\ContactMessage\GetPaginatedContactMessages;

use App\Common\Command\CommandInterface;
use App\Common\UseCase\AbstractUseCase;
use App\Repository\ContactMessageRepository;

/**
 * @extends AbstractUseCase<GetPaginatedContactMessagesCommand>
 */
class GetPaginatedContactMessagesUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly ContactMessageRepository $contactMessageRepository
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        $messages = $this->contactMessageRepository->findPaginatedOrderedByDate(
            $command->page,
            $command->limit,
            $command->search,
        );

        return [
            'data' => array_map(fn($message) => $message->toArray(), $messages),
            'total' => $this->contactMessageRepository->countBySearch($command->search),
        ];
    }
}
