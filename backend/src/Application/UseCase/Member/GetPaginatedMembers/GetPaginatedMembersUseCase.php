<?php

namespace App\Application\UseCase\Member\GetPaginatedMembers;

use App\Common\Command\CommandInterface;
use App\Common\Service\MemberPhotoUrls;
use App\Common\Service\SeasonProvider;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Member;
use App\Repository\LicenseRepository;
use App\Repository\MemberDocumentRepository;
use App\Repository\MemberRepository;
use App\Repository\UserRepository;

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
        private readonly MemberPhotoUrls $photoUrls,
        private readonly UserRepository $userRepository,
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
        // est portée par la licence de la saison.
        $memberIds = array_map(static fn (Member $m) => (int) $m->getId(), $result['data']);
        $licenses = $this->licenseRepository->findBySeasonIndexedByMember($memberIds, $season);
        $accounts = $this->userRepository->findByMembersIndexed($memberIds);

        $photos = $this->photoUrls->forMembers($result['data'], $season);

        return [
            'data' => array_map(
                function (Member $member) use ($season, $licenses, $photos, $accounts) {
                    $license = $licenses[$member->getId()] ?? null;
                    $account = $accounts[$member->getId()] ?? null;

                    return [
                        // "Licence payée" et présence du fichier sont toutes deux
                        // calculées sur la saison courante (cohérence).
                        ...$member->toArray($season),
                        'hasLicenseDocument' => $this->documentRepository
                            ->findDefaultSlot($member, $season, 'license')?->hasFile() ?? false,
                        'fsgtRegistered' => $license?->isFsgtRegistered() ?? false,
                        'profilePictureUrl' => $photos[$member->getId()] ?? null,
                        // Compte de connexion lié (chef d'équipe invité ou actif).
                        'account' => $account ? [
                            'invitationStatus' => $account->getInvitationStatus(),
                            'teamIds' => array_map(fn ($t) => $t->getId(), $account->getTeams()->toArray()),
                        ] : null,
                    ];
                },
                $result['data'],
            ),
            'total' => $result['total'],
        ];
    }
}
