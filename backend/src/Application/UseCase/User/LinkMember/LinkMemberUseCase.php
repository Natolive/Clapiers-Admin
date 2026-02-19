<?php

namespace App\Application\UseCase\User\LinkMember;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\AppUser;
use App\Repository\MemberRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends AbstractUseCase<LinkMemberCommand>
 */
class LinkMemberUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MemberRepository $memberRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function run(?CommandInterface $command = null): AppUser
    {
        if (!$command instanceof LinkMemberCommand) {
            throw new UseCaseException('Invalid command');
        }

        $user = $this->userRepository->find($command->userId);

        if (!$user) {
            throw new UseCaseException('User not found', 404);
        }

        $member = $this->memberRepository->find($command->memberId);

        if (!$member) {
            throw new UseCaseException('Member not found', 404);
        }

        $existingUser = $this->userRepository->findOneBy(['member' => $member]);
        if ($existingUser && $existingUser->getId() !== $user->getId()) {
            throw new UseCaseException('This member is already linked to another user', 409);
        }

        $user->setMember($member);
        $this->entityManager->flush();

        return $user;
    }
}
