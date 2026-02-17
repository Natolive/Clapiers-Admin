<?php

namespace App\Application\UseCase\User\GetPaginatedUsers;

use App\Common\Command\CommandInterface;
use App\Common\UseCase\AbstractUseCase;
use App\Repository\UserRepository;

/**
 * @extends AbstractUseCase<GetPaginatedUsersCommand>
 */
class GetPaginatedUsersUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        $result = $this->userRepository->findPaginated(
            $command->page,
            $command->limit,
            $command->sortField,
            $command->sortOrder,
            $command->search,
        );

        return [
            'data' => array_map(fn($user) => $user->toArray(), $result['data']),
            'total' => $result['total'],
        ];
    }
}
