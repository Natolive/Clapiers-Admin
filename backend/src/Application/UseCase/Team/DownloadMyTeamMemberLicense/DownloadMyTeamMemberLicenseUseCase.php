<?php

namespace App\Application\UseCase\Team\DownloadMyTeamMemberLicense;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Repository\MemberRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @extends AbstractUseCase<DownloadMyTeamMemberLicenseCommand>
 */
class DownloadMyTeamMemberLicenseUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        #[Autowire('%upload_directory%')]
        private readonly string $uploadDirectory
    ) {
    }

    public function run(?CommandInterface $command = null): BinaryFileResponse
    {
        if (!$command instanceof DownloadMyTeamMemberLicenseCommand) {
            throw new UseCaseException('Invalid command');
        }

        $userTeam = $command->user->getTeam();

        if (!$userTeam) {
            throw new UseCaseException('You are not assigned to a team', 403);
        }

        $member = $this->memberRepository->find($command->memberId);

        if (!$member) {
            throw new UseCaseException('Member not found', 404);
        }

        if ($member->getTeam()->getId() !== $userTeam->getId()) {
            throw new UseCaseException('This member is not in your team', 403);
        }

        if (!$member->getLicenseFileName()) {
            throw new UseCaseException('No license file for this member', 404);
        }

        $filePath = $this->uploadDirectory . '/licenses/' . $member->getLicenseFileName();

        if (!file_exists($filePath)) {
            throw new UseCaseException('License file not found on disk', 404);
        }

        return new BinaryFileResponse($filePath);
    }
}
