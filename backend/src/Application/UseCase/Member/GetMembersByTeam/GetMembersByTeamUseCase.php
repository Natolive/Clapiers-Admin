<?php

namespace App\Application\UseCase\Member\GetMembersByTeam;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\SeasonProvider;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Member;
use App\Repository\MemberRepository;
use App\Repository\TeamRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractUseCase<GetMembersByTeamCommand>
 */
class GetMembersByTeamUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly TeamRepository $teamRepository,
        private readonly SeasonProvider $seasonProvider,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function run(?CommandInterface $command = null): array
    {
        $team = $this->teamRepository->find($command->teamId);

        if (!$team) {
            // NotFoundHttpException would be swallowed by execute()'s catch-all
            // and turned into a 500: UseCaseException carries the right status
            throw new UseCaseException('Team not found', Response::HTTP_NOT_FOUND);
        }

        // "Licence payée" alignée sur la saison courante (cohérence avec le reste).
        $season = $this->seasonProvider->current();

        return array_map(
            fn (Member $m) => $m->toArray($season),
            $this->memberRepository->findByTeam($team),
        );
    }
}
