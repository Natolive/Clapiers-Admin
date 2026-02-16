<?php

namespace App\Application\UseCase\Member\DeleteLicense;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Member;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @extends AbstractUseCase<DeleteLicenseCommand>
 */
class DeleteLicenseUseCase extends AbstractUseCase
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
        if (!$command instanceof DeleteLicenseCommand) {
            throw new UseCaseException('Invalid command');
        }

        $member = $this->memberRepository->find($command->memberId);

        if (!$member) {
            throw new UseCaseException('Member not found');
        }

        if ($member->getLicenseFileName()) {
            $filePath = $this->uploadDirectory . '/licenses/' . $member->getLicenseFileName();
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $member->setLicenseFileName(null);
            $this->entityManager->flush();
        }

        return $member;
    }
}
