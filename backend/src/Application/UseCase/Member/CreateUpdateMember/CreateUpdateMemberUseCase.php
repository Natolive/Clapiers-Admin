<?php

namespace App\Application\UseCase\Member\CreateUpdateMember;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Member;
use App\Entity\ValueObject\Address;
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
        $teams = $this->resolveTeams($command);

        // Create member
        $member = new Member();
        $member->setFirstName($command->firstName);
        $member->setLastName($command->lastName);
        $member->setTeams($teams);
        $member->setPhoneNumber($command->phoneNumber);
        $member->setEmail($command->email);
        $member->setLicenseNumber($command->licenseNumber);
        $member->setAddress(new Address($command->addressStreet, $command->addressZip, $command->addressCity));
        $member->setGender($command->gender);
        $member->setBirthDate(new \DateTimeImmutable($command->birthDate));
        $member->setNationality($command->nationality);

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

        $teams = $this->resolveTeams($command);

        $member->setFirstName($command->firstName);
        $member->setLastName($command->lastName);
        $member->setTeams($teams);
        $member->setPhoneNumber($command->phoneNumber);
        $member->setEmail($command->email);
        $member->setLicenseNumber($command->licenseNumber);
        $member->setAddress(new Address($command->addressStreet, $command->addressZip, $command->addressCity));
        $member->setGender($command->gender);
        $member->setBirthDate(new \DateTimeImmutable($command->birthDate));
        $member->setNationality($command->nationality);

        $this->entityManager->flush();

        return $member;
    }

    /**
     * @return list<\App\Entity\Team>
     */
    private function resolveTeams(CreateUpdateMemberCommand $command): array
    {
        $teams = [];
        foreach (array_unique($command->teamIds) as $teamId) {
            $team = $this->teamRepository->find($teamId);
            if (!$team) {
                throw new UseCaseException('Team not found');
            }
            $teams[] = $team;
        }

        return $teams;
    }
}
