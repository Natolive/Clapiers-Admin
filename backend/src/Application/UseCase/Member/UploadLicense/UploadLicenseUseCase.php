<?php

namespace App\Application\UseCase\Member\UploadLicense;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Member;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/**
 * @extends AbstractUseCase<UploadLicenseCommand>
 */
class UploadLicenseUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%upload_directory%')]
        private readonly string $uploadDirectory
    ) {
    }

    public function run(?CommandInterface $command = null): Member
    {
        if (!$command instanceof UploadLicenseCommand) {
            throw new UseCaseException('Invalid command');
        }

        $member = $this->memberRepository->find($command->memberId);

        if (!$member) {
            throw new UseCaseException('Member not found');
        }

        // Delete old file if exists
        if ($member->getLicenseFileName()) {
            $oldFilePath = $this->uploadDirectory . '/licenses/' . $member->getLicenseFileName();
            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }
        }

        // Generate UUID filename keeping original extension
        $extension = $command->file->guessExtension() ?? $command->file->getClientOriginalExtension();
        $fileName = Uuid::v4()->toRfc4122() . '.' . $extension;

        // Move file to upload directory
        $command->file->move(
            $this->uploadDirectory . '/licenses',
            $fileName
        );

        $member->setLicenseFileName($fileName);
        $this->entityManager->flush();

        return $member;
    }
}
