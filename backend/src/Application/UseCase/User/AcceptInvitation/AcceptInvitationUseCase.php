<?php

namespace App\Application\UseCase\User\AcceptInvitation;

use App\Application\UseCase\User\GetInvitation\GetInvitationUseCase;
use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * L'invité choisit son mot de passe ; le lien est consommé. Il se connecte
 * ensuite normalement avec son e-mail.
 *
 * @extends AbstractUseCase<AcceptInvitationCommand>
 */
class AcceptInvitationUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof AcceptInvitationCommand) {
            throw new UseCaseException('Invalid command');
        }

        $user = GetInvitationUseCase::findValid($this->userRepository, $command->token);
        $user->changePassword($this->passwordHasher->hashPassword($user, $command->password));
        $user->clearInvitation();

        $this->entityManager->flush();

        return ['email' => $user->getEmail()];
    }
}
