<?php

namespace App\Application\UseCase\Team\DownloadMyTeamMemberLicense;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\MemberMediaStorage;
use App\Common\Service\SeasonProvider;
use App\Common\UseCase\AbstractUseCase;
use App\Repository\MemberDocumentRepository;
use App\Repository\MemberRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un coach télécharge la licence (saison courante) d'un membre de son équipe.
 * La pièce vit dans la médiathèque ; l'accès reste gardé par appartenance d'équipe.
 *
 * @extends AbstractUseCase<DownloadMyTeamMemberLicenseCommand>
 */
class DownloadMyTeamMemberLicenseUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly MemberDocumentRepository $documentRepository,
        private readonly MemberMediaStorage $storage,
        private readonly SeasonProvider $seasonProvider,
    ) {
    }

    public function run(?CommandInterface $command = null): Response
    {
        if (!$command instanceof DownloadMyTeamMemberLicenseCommand) {
            throw new UseCaseException('Invalid command');
        }

        if ($command->user->getTeams()->isEmpty()) {
            throw new UseCaseException('You are not assigned to a team', 403);
        }

        $member = $this->memberRepository->find($command->memberId);

        if (!$member) {
            throw new UseCaseException('Member not found', 404);
        }

        $sharesTeam = false;
        foreach ($member->getTeams() as $memberTeam) {
            if ($command->user->hasTeam($memberTeam)) {
                $sharesTeam = true;
                break;
            }
        }

        if (!$sharesTeam) {
            throw new UseCaseException('This member is not in your team', 403);
        }

        $slot = $this->documentRepository->findDefaultSlot(
            $member,
            $this->seasonProvider->current(),
            'license',
        );

        if (!$slot || !$slot->hasFile()) {
            throw new UseCaseException('No license file for this member', 404);
        }

        $response = $this->storage->response(
            (string) $slot->getStoredName(),
            $slot->getMimeType(),
            $slot->getOriginalName() ?? 'licence',
        );

        if ($response === null) {
            throw new UseCaseException('License file not found on disk', 404);
        }

        return $response;
    }
}
