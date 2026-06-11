<?php

namespace App\Application\UseCase\Team\GetMyTeam;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Member;
use App\Repository\MemberRepository;

/**
 * @extends AbstractUseCase<GetMyTeamCommand>
 */
class GetMyTeamUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository
    ) {
    }

    /**
     * Retourne les licenciés de chaque équipe gérée par l'utilisateur,
     * groupés par équipe : [{team, members[]}]
     */
    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof GetMyTeamCommand) {
            throw new UseCaseException('Invalid command');
        }

        $groups = [];

        foreach ($command->user->getTeams() as $team) {
            $groups[] = [
                'team' => $team->toArray(),
                'members' => array_map(
                    fn (Member $m) => $m->toArray(),
                    $this->memberRepository->findByTeam($team)
                ),
            ];
        }

        return $groups;
    }
}
