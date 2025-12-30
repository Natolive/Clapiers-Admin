<?php

namespace App\Application\UseCase\Team;

use App\Application\UseCase\Season\GetActualSeasonUseCase;
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
        private readonly TeamRepository $teamRepository,
        private readonly GetActualSeasonUseCase $getActualSeasonUseCase
    ) {
    }

    /**
     * @return array<int, Team>
     */
    public function run(?CommandInterface $command = null): array
    {
        $actualSeason = $this->getActualSeasonUseCase->run();
        return $this->teamRepository->findBySeason($actualSeason);
    }
}
