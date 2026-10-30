<?php

namespace App\Application\UseCase\User\ResetPassword;

use App\Application\UseCase\User\GetPasswordReset\GetPasswordResetUseCase;
use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Pose le nouveau mot de passe et consomme le lien.
 *
 * @extends AbstractUseCase<ResetPasswordCommand>
 */
class ResetPasswordUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof ResetPasswordCommand) {
            throw new UseCaseException('Invalid command');
        }

        $user = GetPasswordResetUseCase::findValid($this->userRepository, $command->token);
        $user->changePassword($this->passwordHasher->hashPassword($user, $command->password));
        $user->clearPasswordReset();

        $this->entityManager->flush();

        return ['email' => $user->getEmail()];
    }
}
