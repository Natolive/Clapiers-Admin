<?php

namespace App\Application\UseCase\Member\GetPaginatedMembers;

use App\Common\Command\CommandInterface;
use App\Common\Service\SeasonProvider;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Member;
use App\Repository\MemberDocumentRepository;
use App\Repository\MemberRepository;

/**
 * @extends AbstractUseCase<GetPaginatedMembersCommand>
 */
class GetPaginatedMembersUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly MemberDocumentRepository $documentRepository,
        private readonly SeasonProvider $seasonProvider,
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
            $season = $this->seasonProvider->current(),
        );

        return [
            'data' => array_map(
                fn (Member $member) => [
                    // "Licence payée" et présence du fichier sont toutes deux
                    // calculées sur la saison courante (cohérence).
                    ...$member->toArray($season),
                    'hasLicenseDocument' => $this->documentRepository
                        ->findDefaultSlot($member, $season, 'license')?->hasFile() ?? false,
                ],
                $result['data'],
            ),
            'total' => $result['total'],
        ];
    }
}
