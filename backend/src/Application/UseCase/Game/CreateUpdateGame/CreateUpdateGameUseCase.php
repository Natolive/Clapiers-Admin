<?php

namespace App\Application\UseCase\Game\CreateUpdateGame;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Enum\AppUserRole;
use App\Entity\Enum\GameVenue;
use App\Entity\Game;
use App\Entity\Team;
use App\Repository\GameRepository;
use App\Repository\TeamRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractUseCase<CreateUpdateGameCommand>
 */
class CreateUpdateGameUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly GameRepository         $gameRepository,
        private readonly TeamRepository         $teamRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function run(?CommandInterface $command = null): Game
    {
        if (!$command instanceof CreateUpdateGameCommand) {
            throw new UseCaseException('Invalid command');
        }

        if ($command->id === null) {
            return $this->createGame($command);
        }

        return $this->updateGame($command);
    }

    private function assertTeamDailyLimit(Team $team, string $date, ?int $excludeId): void
    {
        $count = $this->gameRepository->countGamesByTeamAndDate($team, new DateTimeImmutable($date), $excludeId);

        if ($count >= 1) {
            throw new UseCaseException(
                'Cette équipe a déjà un match planifié ce jour.',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    private function assertHomeGameLimit(string $date, GameVenue $venue, ?int $excludeId): void
    {
        if ($venue !== GameVenue::HOME) {
            return;
        }

        $count = $this->gameRepository->countHomeGamesByDate(new DateTimeImmutable($date), $excludeId);

        if ($count >= 3) {
            throw new UseCaseException(
                'Le nombre maximum de matchs à domicile pour ce jour est atteint (3/3)',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    // Création/édition complète : super admin uniquement (garanti par le contrôleur).
    private function resolveTeam(CreateUpdateGameCommand $command): Team
    {
        if ($command->teamId === null) {
            throw new UseCaseException('Team is required');
        }

        $team = $this->teamRepository->find($command->teamId);
        if (!$team) {
            throw new UseCaseException('Team not found', Response::HTTP_NOT_FOUND);
        }

        return $team;
    }

    private function createGame(CreateUpdateGameCommand $command): Game
    {
        $team = $this->resolveTeam($command);
        $this->assertTeamDailyLimit($team, $command->date, null);
        $this->assertHomeGameLimit($command->date, $command->venue, null);

        $game = new Game();
        $this->hydrate($game, $command, $team);

        $this->entityManager->persist($game);
        $this->entityManager->flush();

        return $game;
    }

    private function updateGame(CreateUpdateGameCommand $command): Game
    {
        $game = $this->gameRepository->find($command->id);
        if (!$game) {
            throw new UseCaseException('Game not found', Response::HTTP_NOT_FOUND);
        }

        $isSuperAdmin = in_array(AppUserRole::ROLE_SUPER_ADMIN, $command->user->getRoles(), true);

        if ($isSuperAdmin) {
            $team = $this->resolveTeam($command);
            $this->assertTeamDailyLimit($team, $command->date, $command->id);
            $this->assertHomeGameLimit($command->date, $command->venue, $command->id);
            $this->hydrate($game, $command, $team);
        } else {
            // Admin : replanification uniquement — seule la date change, sur ses propres équipes
            if (!$command->user->hasTeam($game->getTeam())) {
                throw new UseCaseException('You are not allowed to modify this game', Response::HTTP_FORBIDDEN);
            }
            $this->assertTeamDailyLimit($game->getTeam(), $command->date, $command->id);
            $this->assertHomeGameLimit($command->date, $game->getVenue(), $command->id);
            $game->setDate(new DateTimeImmutable($command->date));
        }

        $this->entityManager->flush();

        return $game;
    }

    private function hydrate(Game $game, CreateUpdateGameCommand $command, \App\Entity\Team $team): void
    {
        $game->setOpponent($command->opponent);
        $game->setDate(new DateTimeImmutable($command->date));
        $game->setMeetingTime($command->meetingTime);
        $game->setVenue($command->venue);
        $game->setLocation($command->location);
        $game->setTeam($team);
    }
}
