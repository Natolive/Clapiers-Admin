<?php

namespace App\Application\UseCase\Member\GetPaginatedMembers;

use App\Common\Command\CommandInterface;
use App\Common\Service\SeasonProvider;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Member;
use App\Repository\LicenseRepository;
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
        private readonly LicenseRepository $licenseRepository,
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
            // Saison choisie côté client, sinon la saison courante.
            $season = $command->season ?: $this->seasonProvider->current(),
            $command->fsgtRegistered,
        );

        // Une requête pour toutes les licences de la page : l'inscription FSGT
        // et son numéro sont portés par la licence de la saison.
        $licenses = $this->licenseRepository->findBySeasonIndexedByMember(
            array_map(static fn (Member $m) => (int) $m->getId(), $result['data']),
            $season,
        );

        return [
            'data' => array_map(
                function (Member $member) use ($season, $licenses) {
                    $license = $licenses[$member->getId()] ?? null;

                    return [
                        // "Licence payée" et présence du fichier sont toutes deux
                        // calculées sur la saison courante (cohérence).
                        ...$member->toArray($season),
                        'hasLicenseDocument' => $this->documentRepository
                            ->findDefaultSlot($member, $season, 'license')?->hasFile() ?? false,
                        'fsgtRegistered' => $license?->isFsgtRegistered() ?? false,
                        // Le numéro de la saison ; celui de la fiche ne sert que
                        // de repli pour les membres saisis à la main.
                        'seasonLicenseNumber' => $license?->getLicenseNumber(),
                    ];
                },
                $result['data'],
            ),
            'total' => $result['total'],
        ];
    }
}
