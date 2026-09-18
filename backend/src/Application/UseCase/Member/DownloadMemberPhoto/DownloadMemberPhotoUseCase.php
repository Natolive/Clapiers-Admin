<?php

namespace App\Application\UseCase\Member\DownloadMemberPhoto;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\MemberMediaStorage;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Enum\AppUserRole;
use App\Repository\MemberDocumentRepository;
use App\Repository\MemberRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sert la photo de profil d'un licencié (slot médiathèque « Photo d'identité »,
 * source unique).
 *
 * Deux publics, une seule règle : le SUPER_ADMIN voit tout le club ; un coach
 * (ROLE_ADMIN) ne voit que les licenciés d'une de ses équipes. C'est la même
 * garde que le téléchargement de licence — la photo est une donnée personnelle,
 * pas un trombinoscope public.
 *
 * @extends AbstractUseCase<DownloadMemberPhotoCommand>
 */
class DownloadMemberPhotoUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly MemberDocumentRepository $documentRepository,
        private readonly MemberMediaStorage $storage,
    ) {
    }

    public function run(?CommandInterface $command = null): Response
    {
        if (!$command instanceof DownloadMemberPhotoCommand) {
            throw new UseCaseException('Invalid command');
        }

        $member = $this->memberRepository->find($command->memberId);

        if (!$member) {
            throw new UseCaseException('Member not found', Response::HTTP_NOT_FOUND);
        }

        if (!$this->maySee($command, $member->getTeams()->toArray())) {
            throw new UseCaseException('This member is not in your team', Response::HTTP_FORBIDDEN);
        }

        $slot = $this->documentRepository->findRootDocumentSlot($member, 'identity_photo');

        if (!$slot || !$slot->hasFile()) {
            throw new UseCaseException('No profile picture for this member', Response::HTTP_NOT_FOUND);
        }

        $response = $this->storage->response((string) $slot->getStoredName(), $slot->getMimeType());

        if ($response === null) {
            throw new UseCaseException('Profile picture not found on disk', Response::HTTP_NOT_FOUND);
        }

        return $response;
    }

    /** @param list<\App\Entity\Team> $memberTeams */
    private function maySee(DownloadMemberPhotoCommand $command, array $memberTeams): bool
    {
        if (in_array(AppUserRole::ROLE_SUPER_ADMIN, $command->user->getRoles(), true)) {
            return true;
        }

        foreach ($memberTeams as $team) {
            if ($command->user->hasTeam($team)) {
                return true;
            }
        }

        return false;
    }
}
