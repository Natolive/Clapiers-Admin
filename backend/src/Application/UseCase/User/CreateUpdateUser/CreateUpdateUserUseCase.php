<?php

namespace App\Application\UseCase\User\CreateUpdateUser;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\AppUser;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @extends AbstractUseCase<CreateUpdateUserCommand>
 */
class CreateUpdateUserUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function run(?CommandInterface $command = null): AppUser
    {
        if (!$command instanceof CreateUpdateUserCommand) {
            throw new UseCaseException('Invalid command');
        }

        if ($command->id === null) {
            // Create new user
            return $this->createUser($command);
        }

        // Update existing user
        return $this->updateUser($command);
    }

    private function createUser(CreateUpdateUserCommand $command): AppUser
    {
        // Validate password is provided for new users
        if (empty($command->password)) {
            throw new UseCaseException('Password is required for new users');
        }

        // Check if email already exists
        $existingUser = $this->userRepository->findOneBy(['email' => $command->email]);
        if ($existingUser) {
            throw new UseCaseException('Email already exists');
        }

        $user = new AppUser();
        $user->setEmail($command->email);
        $user->setRoles([$command->role]);

        // Hash password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $command->password);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function updateUser(CreateUpdateUserCommand $command): AppUser
    {
        $user = $this->userRepository->find($command->id);

        if (!$user) {
            throw new UseCaseException('User not found');
        }

        // Check if email is being changed and if it already exists
        if ($user->getEmail() !== $command->email) {
            $existingUser = $this->userRepository->findOneBy(['email' => $command->email]);
            if ($existingUser) {
                throw new UseCaseException('Email already exists');
            }
            $user->setEmail($command->email);
        }

        $user->setRoles([$command->role]);

        // Only update password if provided
        if (!empty($command->password)) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $command->password);
            $user->setPassword($hashedPassword);
        }

        $this->entityManager->flush();

        return $user;
    }
}
