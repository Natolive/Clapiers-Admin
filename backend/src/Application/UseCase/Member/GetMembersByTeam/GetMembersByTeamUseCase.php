<?php

namespace App\Application\UseCase\Member\GetMembersByTeam;

use App\Common\Command\CommandInterface;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Member;
use App\Repository\MemberRepository;
use App\Repository\TeamRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @extends AbstractUseCase<GetMembersByTeamCommand>
 */
class GetMembersByTeamUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly TeamRepository $teamRepository
    ) {
    }

    /**
     * @return array<int, Member>
     */
    public function run(?CommandInterface $command = null): array
    {
        $team = $this->teamRepository->find($command->teamId);

        if (!$team) {
            throw new NotFoundHttpException('Team not found');
        }

        return $this->memberRepository->findByTeam($team);
    }
}
