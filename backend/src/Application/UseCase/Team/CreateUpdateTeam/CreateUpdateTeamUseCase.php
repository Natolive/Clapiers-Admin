<?php

namespace App\Application\UseCase\Team\CreateUpdateTeam;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Team;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractUseCase<CreateUpdateTeamCommand>
 */
class CreateUpdateTeamUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly UserRepository $userRepository,
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
        $this->syncCoaches($team, $command->userIds);
        $this->entityManager->flush();

        return $team;
    }

    private function updateTeam(CreateUpdateTeamCommand $command): Team
    {
        $team = $this->teamRepository->find($command->id);

        if (!$team) {
            throw new UseCaseException('Team not found', Response::HTTP_NOT_FOUND);
        }

        $team->setName($command->name);
        $this->syncCoaches($team, $command->userIds);

        $this->entityManager->flush();

        return $team;
    }

    /**
     * Réconcilie les coachs de l'équipe avec la liste fournie. La relation est
     * portée par AppUser.teams (côté propriétaire) : on modifie chaque user.
     *
     * @param list<int>|null $userIds null = ne pas toucher aux coachs
     */
    private function syncCoaches(Team $team, ?array $userIds): void
    {
        if ($userIds === null) {
            return;
        }

        $targets = [];
        foreach (array_unique($userIds) as $userId) {
            $user = $this->userRepository->find($userId);
            if (!$user) {
                throw new UseCaseException(sprintf('User %d not found', $userId), Response::HTTP_NOT_FOUND);
            }
            $targets[$userId] = $user;
        }

        // Retire l'équipe aux coachs qui n'y sont plus.
        foreach ($team->getCoaches() as $current) {
            if (!isset($targets[$current->getId()])) {
                $current->removeTeam($team);
            }
        }

        // Ajoute l'équipe aux nouveaux coachs.
        foreach ($targets as $user) {
            $user->addTeam($team);
        }
    }
}
