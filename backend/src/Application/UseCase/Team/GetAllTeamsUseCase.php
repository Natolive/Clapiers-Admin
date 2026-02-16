<?php

namespace App\Application\UseCase\Team;

use App\Common\Command\CommandInterface;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Team;
use App\Repository\TeamRepository;

/**
 * @extends AbstractUseCase<null>
 */
class GetAllTeamsUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly TeamRepository $teamRepository
    ) {
    }

    /**
     * @return array<int, Team>
     */
    public function run(?CommandInterface $command = null): array
    {
        return $this->teamRepository->findAll();
    }
}
