<?php

namespace App\Application\UseCase\Member\DeleteProfilePicture;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Member;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @extends AbstractUseCase<DeleteProfilePictureCommand>
 */
class DeleteProfilePictureUseCase extends AbstractUseCase
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
        if (!$command instanceof DeleteProfilePictureCommand) {
            throw new UseCaseException('Invalid command');
        }

        $member = $this->memberRepository->find($command->memberId);

        if (!$member) {
            throw new UseCaseException('Member not found');
        }

        if ($member->getProfilePicture()) {
            $filePath = $this->uploadDirectory . '/profile-pictures/' . $member->getProfilePicture();
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $member->setProfilePicture(null);
            $this->entityManager->flush();
        }

        return $member;
    }
}
