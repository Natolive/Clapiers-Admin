<?php

namespace App\Application\UseCase\Game\GetGames;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Repository\GameRepository;
use App\Repository\TeamRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractUseCase<GetGamesCommand>
 */
class GetGamesUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly GameRepository $gameRepository,
        private readonly TeamRepository $teamRepository,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof GetGamesCommand) {
            throw new UseCaseException('Invalid command');
        }

        // Both admin and super admin can see all games, with optional team filter
        if ($command->teamId !== null) {
            $team = $this->teamRepository->find($command->teamId);
            if (!$team) {
                throw new UseCaseException('Team not found', Response::HTTP_NOT_FOUND);
            }
            return $this->gameRepository->findByTeamAndDateRange($team, $command->start, $command->end);
        }

        return $this->gameRepository->findAllByDateRange($command->start, $command->end);
    }
}
