<?php

namespace App\Application\UseCase\Team\UpdateTeamRoster;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\MemberPhotoUrls;
use App\Common\Service\SeasonProvider;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Member;
use App\Repository\MemberRepository;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Compose l'effectif depuis l'équipe (l'inverse du champ « équipes » de la
 * fiche licencié), en ajouts/retraits explicites.
 *
 * Volontairement pas un « remplace la liste » : l'effectif affiché est celui
 * d'une saison, alors que le rattachement `member_team` ne l'est pas. Envoyer
 * la liste vue à l'écran détacherait silencieusement les licenciés des autres
 * saisons.
 *
 * @extends AbstractUseCase<UpdateTeamRosterCommand>
 */
class UpdateTeamRosterUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly MemberRepository $memberRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SeasonProvider $seasonProvider,
        private readonly MemberPhotoUrls $photoUrls,
    ) {
    }

    /**
     * @return array<string, mixed> l'équipe, ses compteurs et son effectif de la saison
     */
    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof UpdateTeamRosterCommand) {
            throw new UseCaseException('Invalid command');
        }

        $team = $this->teamRepository->find($command->teamId);
        if (!$team) {
            throw new UseCaseException('Team not found', Response::HTTP_NOT_FOUND);
        }

        foreach ($this->findMembers($command->addMemberIds) as $member) {
            $member->addTeam($team);
        }

        foreach ($this->findMembers($command->removeMemberIds) as $member) {
            $member->removeTeam($team);
        }

        $this->entityManager->flush();

        $season = $command->season ?? $this->seasonProvider->current();
        $members = $this->memberRepository->findByTeam($team, $season);
        $counts = $this->memberRepository->countActiveByTeam($season);
        $photos = $this->photoUrls->forMembers($members, $season);

        return [
            ...$team->toArray(),
            'season' => $season,
            'memberCount' => $counts[$team->getId()]['members'] ?? 0,
            'paidCount' => $counts[$team->getId()]['paid'] ?? 0,
            'members' => array_map(
                fn (Member $m) => [
                    ...$m->toArray($season),
                    'profilePictureUrl' => $photos[$m->getId()] ?? null,
                ],
                $members,
            ),
        ];
    }

    /**
     * @param list<int> $ids
     * @return list<Member>
     */
    private function findMembers(array $ids): array
    {
        $members = [];

        foreach (array_unique($ids) as $id) {
            $member = $this->memberRepository->find($id);
            if (!$member) {
                throw new UseCaseException(sprintf('Member %d not found', $id), Response::HTTP_NOT_FOUND);
            }
            $members[] = $member;
        }

        return $members;
    }
}
