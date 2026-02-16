<?php

namespace App\Application\UseCase\Team\CreateUpdateTeam;

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
            return $this->createTeam($command);
        }

        return $this->updateTeam($command);
    }

    private function createTeam(CreateUpdateTeamCommand $command): Team
    {
        $team = new Team();
        $team->setName($command->name);

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
