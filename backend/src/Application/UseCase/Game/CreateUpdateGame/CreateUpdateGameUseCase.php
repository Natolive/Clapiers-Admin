<?php

namespace App\Application\UseCase\Game\CreateUpdateGame;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Enum\AppUserRole;
use App\Entity\Enum\GameVenue;
use App\Entity\Game;
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

        $team = $this->resolveTeam($command);
        $this->assertTeamDailyLimit($team, $command);
        $this->assertHomeGameLimit($command);

        if ($command->id === null) {
            return $this->createGame($command, $team);
        }

        return $this->updateGame($command, $team);
    }

    private function assertTeamDailyLimit(\App\Entity\Team $team, CreateUpdateGameCommand $command): void
    {
        $date  = new DateTimeImmutable($command->date);
        $count = $this->gameRepository->countGamesByTeamAndDate($team, $date, $command->id);

        if ($count >= 1) {
            throw new UseCaseException(
                'Cette équipe a déjà un match planifié ce jour.',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    private function assertHomeGameLimit(CreateUpdateGameCommand $command): void
    {
        if ($command->venue !== GameVenue::HOME) {
            return;
        }

        $date  = new DateTimeImmutable($command->date);
        $count = $this->gameRepository->countHomeGamesByDate($date, $command->id);

        if ($count >= 3) {
            throw new UseCaseException(
                'Le nombre maximum de matchs à domicile pour ce jour est atteint (3/3)',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    private function resolveTeam(CreateUpdateGameCommand $command): \App\Entity\Team
    {
        $isSuperAdmin = in_array(AppUserRole::ROLE_SUPER_ADMIN, $command->user->getRoles(), true);

        if ($isSuperAdmin) {
            if ($command->teamId === null) {
                throw new UseCaseException('Team is required');
            }
            $team = $this->teamRepository->find($command->teamId);
            if (!$team) {
                throw new UseCaseException('Team not found', Response::HTTP_NOT_FOUND);
            }
            return $team;
        }

        // Admin: must use their own team
        $member = $command->user->getMember();
        if (!$member || !$member->getTeam()) {
            throw new UseCaseException('You are not linked to a team', Response::HTTP_FORBIDDEN);
        }

        return $member->getTeam();
    }

    private function assertOwnership(Game $game, CreateUpdateGameCommand $command): void
    {
        $isSuperAdmin = in_array(AppUserRole::ROLE_SUPER_ADMIN, $command->user->getRoles(), true);
        if ($isSuperAdmin) {
            return;
        }

        $member = $command->user->getMember();
        if (!$member || !$member->getTeam() || $member->getTeam()->getId() !== $game->getTeam()->getId()) {
            throw new UseCaseException('You are not allowed to modify this game', Response::HTTP_FORBIDDEN);
        }
    }

    private function createGame(CreateUpdateGameCommand $command, \App\Entity\Team $team): Game
    {
        $game = new Game();
        $this->hydrate($game, $command, $team);

        $this->entityManager->persist($game);
        $this->entityManager->flush();

        return $game;
    }

    private function updateGame(CreateUpdateGameCommand $command, \App\Entity\Team $team): Game
    {
        $game = $this->gameRepository->find($command->id);
        if (!$game) {
            throw new UseCaseException('Game not found', Response::HTTP_NOT_FOUND);
        }

        $this->assertOwnership($game, $command);
        $this->hydrate($game, $command, $team);

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
