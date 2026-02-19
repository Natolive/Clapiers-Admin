<?php

namespace App\Application\UseCase\User\UnlinkMember;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\AppUser;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends AbstractUseCase<UnlinkMemberCommand>
 */
class UnlinkMemberUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function run(?CommandInterface $command = null): AppUser
    {
        if (!$command instanceof UnlinkMemberCommand) {
            throw new UseCaseException('Invalid command');
        }

        $user = $this->userRepository->find($command->userId);

        if (!$user) {
            throw new UseCaseException('User not found', 404);
        }

        $user->setMember(null);
        $this->entityManager->flush();

        return $user;
    }
}
