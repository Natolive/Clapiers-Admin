<?php

namespace App\Application\UseCase\Member\GetPaginatedMembers;

use App\Common\Command\CommandInterface;
use App\Common\UseCase\AbstractUseCase;
use App\Repository\MemberRepository;

/**
 * @extends AbstractUseCase<GetPaginatedMembersCommand>
 */
class GetPaginatedMembersUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        $result = $this->memberRepository->findPaginated(
            $command->page,
            $command->limit,
            $command->sortField,
            $command->sortOrder,
            $command->search,
            $command->teamId,
            $command->licensePaid,
            $command->hasLicense,
        );

        return [
            'data' => array_map(fn($member) => $member->toArray(), $result['data']),
            'total' => $result['total'],
        ];
    }
}
