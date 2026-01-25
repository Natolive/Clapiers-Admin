<?php

namespace App\Application\UseCase\ContactMessage\MarkMessageAsRead;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\ContactMessage;
use App\Repository\ContactMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @extends AbstractUseCase<MarkMessageAsReadCommand>
 */
class MarkMessageAsReadUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly ContactMessageRepository $contactMessageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security
    ) {
    }

    public function run(?CommandInterface $command = null): ContactMessage
    {
        if (!$command instanceof MarkMessageAsReadCommand) {
            throw new UseCaseException('Invalid command');
        }

        $message = $this->contactMessageRepository->find($command->messageId);

        if (!$message) {
            throw new UseCaseException('Message not found');
        }

        $user = $this->security->getUser();

        if (!$user) {
            throw new UseCaseException('User not authenticated');
        }

        $message->markAsRead($user);

        $this->entityManager->flush();

        return $message;
    }
}
