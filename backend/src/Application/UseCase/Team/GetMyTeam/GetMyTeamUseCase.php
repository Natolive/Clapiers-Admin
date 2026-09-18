<?php

namespace App\Application\UseCase\Team\GetMyTeam;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\MemberMediaStorage;
use App\Common\Service\MemberPhotoUrls;
use App\Common\Service\SeasonProvider;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Member;
use App\Repository\MemberDocumentRepository;
use App\Repository\MemberRepository;

/**
 * @extends AbstractUseCase<GetMyTeamCommand>
 */
class GetMyTeamUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly MemberDocumentRepository $documentRepository,
        private readonly SeasonProvider $seasonProvider,
        private readonly MemberPhotoUrls $photoUrls,
        private readonly MemberMediaStorage $storage,
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

        $season = $this->seasonProvider->current();
        $groups = [];

        foreach ($command->user->getTeams() as $team) {
            $members = $this->memberRepository->findByTeam($team, $season);
            $photos = $this->photoUrls->forMembers($members, $season);

            $groups[] = [
                'team' => $team->toArray(),
                'members' => array_map(
                    function (Member $m) use ($season, $photos): array {
                        // "Licence payée" et pièces : toutes sur la saison
                        // courante (médiathèque = source de vérité).
                        $license = $this->documentRepository->findDefaultSlot($m, $season, 'license');

                        return [
                            ...$m->toArray($season),
                            'hasLicenseDocument' => $license?->hasFile() ?? false,
                            'hasProfilePicture' => isset($photos[$m->getId()]),
                            'profilePictureUrl' => $photos[$m->getId()] ?? null,
                            'licenseUrl' => $this->storage->signedUrl(
                                $license?->getStoredName(),
                                MemberMediaStorage::DISPLAY_TTL,
                            ),
                        ];
                    },
                    $members,
                ),
            ];
        }

        return $groups;
    }
}
