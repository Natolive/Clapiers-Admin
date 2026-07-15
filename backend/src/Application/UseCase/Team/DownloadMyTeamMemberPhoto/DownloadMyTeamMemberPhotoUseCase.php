<?php

namespace App\Application\UseCase\Team\DownloadMyTeamMemberPhoto;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\MemberMediaStorage;
use App\Common\UseCase\AbstractUseCase;
use App\Repository\MemberDocumentRepository;
use App\Repository\MemberRepository;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Un coach affiche la photo de profil (médiathèque) d'un membre de son équipe.
 * Accès gardé par appartenance d'équipe, comme le téléchargement de licence.
 *
 * @extends AbstractUseCase<DownloadMyTeamMemberPhotoCommand>
 */
class DownloadMyTeamMemberPhotoUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly MemberDocumentRepository $documentRepository,
        private readonly MemberMediaStorage $storage,
    ) {
    }

    public function run(?CommandInterface $command = null): BinaryFileResponse
    {
        if (!$command instanceof DownloadMyTeamMemberPhotoCommand) {
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

        $slot = $this->documentRepository->findRootDocumentSlot($member, 'profile_picture');

        if (!$slot || !$slot->hasFile()) {
            throw new UseCaseException('No profile picture for this member', 404);
        }

        $path = $this->storage->path((string) $slot->getStoredName());

        if (!is_file($path)) {
            throw new UseCaseException('Profile picture not found on disk', 404);
        }

        return new BinaryFileResponse($path);
    }
}
