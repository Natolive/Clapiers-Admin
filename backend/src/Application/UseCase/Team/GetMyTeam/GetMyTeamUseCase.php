<?php

namespace App\Application\UseCase\Team\GetMyTeam;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
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

    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof GetMyTeamCommand) {
            throw new UseCaseException('Invalid command');
        }

        $team = $command->user->getTeam();

        if (!$team) {
            return [];
        }

        return $this->memberRepository->findByTeam($team);
    }
}
