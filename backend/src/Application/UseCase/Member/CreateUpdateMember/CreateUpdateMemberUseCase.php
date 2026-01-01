<?php

namespace App\Application\UseCase\Member\CreateUpdateMember;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Member;
use App\Repository\MemberRepository;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends AbstractUseCase<CreateUpdateMemberCommand>
 */
class CreateUpdateMemberUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly TeamRepository $teamRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function run(?CommandInterface $command = null): Member
    {
        if (!$command instanceof CreateUpdateMemberCommand) {
            throw new UseCaseException('Invalid command');
        }

        if ($command->id === null) {
            // Create new member
            return $this->createMember($command);
        }

        // Update existing member
        return $this->updateMember($command);
    }

    private function createMember(CreateUpdateMemberCommand $command): Member
    {
        // Get team
        $team = $this->teamRepository->find($command->teamId);

        if (!$team) {
            throw new UseCaseException('Team not found');
        }

        // Create member
        $member = new Member();
        $member->setFirstName($command->firstName);
        $member->setLastName($command->lastName);
        $member->setTeam($team);

        $this->entityManager->persist($member);
        $this->entityManager->flush();

        return $member;
    }

    private function updateMember(CreateUpdateMemberCommand $command): Member
    {
        $member = $this->memberRepository->find($command->id);

        if (!$member) {
            throw new UseCaseException('Member not found');
        }

        // Get team
        $team = $this->teamRepository->find($command->teamId);

        if (!$team) {
            throw new UseCaseException('Team not found');
        }

        $member->setFirstName($command->firstName);
        $member->setLastName($command->lastName);
        $member->setTeam($team);

        $this->entityManager->flush();

        return $member;
    }
}
