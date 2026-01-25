<?php

namespace App\Application\UseCase\ContactMessage\CreateContactMessage;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\ContactMessage;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends AbstractUseCase<CreateContactMessageCommand>
 */
class CreateContactMessageUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function run(?CommandInterface $command = null): ContactMessage
    {
        if (!$command instanceof CreateContactMessageCommand) {
            throw new UseCaseException('Invalid command');
        }

        $message = new ContactMessage();
        $message->setFirstName($command->firstName);
        $message->setLastName($command->lastName);
        $message->setEmail($command->email);
        $message->setSubject($command->subject);
        $message->setMessage($command->message);

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }
}
