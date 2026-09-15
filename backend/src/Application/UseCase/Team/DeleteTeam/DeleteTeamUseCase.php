<?php

namespace App\Application\UseCase\Team\DeleteTeam;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Member;
use App\Repository\MemberRepository;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suppression douce d'une équipe.
 *
 * Elle reste référencée par les matchs joués (`game.team_id` est non nul) :
 * une vraie suppression casserait l'historique. `remove()` est donc intercepté
 * par l'écouteur SoftDeleteable, qui horodate `deletedAt` ; le filtre masque
 * ensuite l'équipe de toutes les lectures.
 *
 * Les jointures (`member_team`, `app_user_team`) ne sont ni des entités ni
 * couvertes par le filtre : on les vide explicitement, sinon un licencié ou un
 * coach resterait rattaché à une équipe qui n'existe plus — même raisonnement
 * que pour la suppression d'un licencié.
 *
 * @extends AbstractUseCase<DeleteTeamCommand>
 */
class DeleteTeamUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly MemberRepository $memberRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{id: int, deleted: true}
     */
    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof DeleteTeamCommand) {
            throw new UseCaseException('Invalid command');
        }

        $team = $this->teamRepository->find($command->id);
        if (!$team) {
            throw new UseCaseException('Team not found', Response::HTTP_NOT_FOUND);
        }

        foreach ($this->memberRepository->findByTeam($team) as $member) {
            $member->removeTeam($team);
        }

        // Côté coachs, la relation est portée par AppUser.teams.
        foreach ($team->getCoaches() as $coach) {
            $coach->removeTeam($team);
        }

        $this->entityManager->flush();

        $this->entityManager->remove($team);
        $this->entityManager->flush();

        return ['id' => $command->id, 'deleted' => true];
    }
}
