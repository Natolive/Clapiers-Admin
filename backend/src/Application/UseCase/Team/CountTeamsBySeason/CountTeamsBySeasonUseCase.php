<?php

namespace App\Application\UseCase\Team\CountTeamsBySeason;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Repository\SeasonRepository;
use App\Repository\TeamRepository;

/**
 * @extends AbstractUseCase<CountTeamsBySeasonCommand>
 */
class CountTeamsBySeasonUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly SeasonRepository $seasonRepository
    ) {
    }

    public function run(?CommandInterface $command = null): int
    {
        if (!$command instanceof CountTeamsBySeasonCommand) {
            throw new UseCaseException('Invalid command');
        }

        $season = $this->seasonRepository->find($command->seasonId);

        if (!$season) {
            throw new UseCaseException('Season not found');
        }

        return $this->teamRepository->countBySeason($season);
    }
}
