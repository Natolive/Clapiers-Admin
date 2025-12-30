<?php

namespace App\Application\UseCase\Team\CreateUpdateTeam;

use App\Application\UseCase\Season\GetActualSeasonUseCase;
use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Team;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends AbstractUseCase<CreateUpdateTeamCommand>
 */
class CreateUpdateTeamUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly GetActualSeasonUseCase $getActualSeasonUseCase,
        private readonly TeamRepository $teamRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function run(?CommandInterface $command = null): Team
    {
        if (!$command instanceof CreateUpdateTeamCommand) {
            throw new UseCaseException('Invalid command');
        }

        if ($command->id === null) {
            // Create new team
            return $this->createTeam($command);
        }

        // Update existing team
        return $this->updateTeam($command);
    }

    private function createTeam(CreateUpdateTeamCommand $command): Team
    {
        // Get actual season using the use case
        $actualSeason = $this->getActualSeasonUseCase->run();

        // Create team
        $team = new Team();
        $team->setName($command->name);
        $team->setSeason($actualSeason);

        $this->entityManager->persist($team);
        $this->entityManager->flush();

        return $team;
    }

    private function updateTeam(CreateUpdateTeamCommand $command): Team
    {
        $team = $this->teamRepository->find($command->id);

        if (!$team) {
            throw new UseCaseException('Team not found');
        }

        $team->setName($command->name);

        $this->entityManager->flush();

        return $team;
    }
}
