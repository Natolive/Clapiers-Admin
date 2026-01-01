<?php

namespace App\Application\UseCase\User;

use App\Common\Command\CommandInterface;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\AppUser;
use App\Repository\UserRepository;

/**
 * @extends AbstractUseCase<null>
 */
class GetAllUsersUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {
    }

    /**
     * @return array<int, AppUser>
     */
    public function run(?CommandInterface $command = null): array
    {
        return $this->userRepository->findAll();
    }
}
