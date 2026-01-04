<?php

namespace App\Application\UseCase\Member\CountMembersBySeason;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Repository\MemberRepository;
use App\Repository\SeasonRepository;

/**
 * @extends AbstractUseCase<CountMembersBySeasonCommand>
 */
class CountMembersBySeasonUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly SeasonRepository $seasonRepository
    ) {
    }

    public function run(?CommandInterface $command = null): int
    {
        if (!$command instanceof CountMembersBySeasonCommand) {
            throw new UseCaseException('Invalid command');
        }

        $season = $this->seasonRepository->find($command->seasonId);

        if (!$season) {
            throw new UseCaseException('Season not found');
        }

        return $this->memberRepository->countBySeason($season);
    }
}
