<?php

namespace App\Application\UseCase\Member\CreateUpdateMember;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\LicensePaymentLinkMailer;
use App\Common\Service\SeasonProvider;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Enum\LicenseStatus;
use App\Entity\License;
use App\Entity\Member;
use App\Entity\ValueObject\Address;
use App\Repository\LicenseRepository;
use App\Repository\MemberRepository;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractUseCase<CreateUpdateMemberCommand>
 */
class CreateUpdateMemberUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly TeamRepository $teamRepository,
        private readonly LicenseRepository $licenseRepository,
        private readonly SeasonProvider $seasonProvider,
        private readonly LicensePaymentLinkMailer $mailer,
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

        if ($command->license !== null) {
            $this->createLicense($member, $command->license);
        }

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

        // Aussi à la modification : c'est la seule façon de rattraper une fiche
        // créée sans licence, invisible dans des listes toutes scopées saison.
        if ($command->license !== null) {
            $this->createLicense($member, $command->license);
        }

        return $member;
    }

    /**
     * Licence saisie à la main par un admin : rien à instruire, donc VALIDEE
     * d'emblée — le statut qui compte comme adhésion de la saison (cf.
     * LicenseStatus::activeMembership()). Le jeton est créé dans tous les cas :
     * c'est lui qui porte le lien de paiement, envoyé maintenant ou renvoyé
     * plus tard depuis les demandes de licence.
     */
    private function createLicense(Member $member, NewMemberLicense $input): void
    {
        $season = $input->season ?: $this->seasonProvider->current();

        if ($this->licenseRepository->findOneByMemberAndSeason($member, $season) !== null) {
            throw new UseCaseException(
                sprintf('Ce licencié a déjà une licence pour la saison %s.', $season),
                Response::HTTP_CONFLICT,
            );
        }

        // Le mail annonce un montant à régler : sans tarif il annoncerait 0 €.
        if ($input->sendPaymentEmail && $input->amount === null) {
            throw new UseCaseException(
                'Choisissez un tarif pour envoyer le lien de paiement.',
                Response::HTTP_BAD_REQUEST,
            );
        }

        $license = new License();
        $license->setMember($member);
        $license->setSeason($season);
        $license->setStatus(LicenseStatus::VALIDEE);
        $license->setApprovedAt(new \DateTimeImmutable('now'));
        $license->setHelloAssoTierId($input->helloAssoTierId);
        $license->setAmount($input->amount);
        $license->setAccessToken(bin2hex(random_bytes(32)));
        $license->extendTokenValidity();

        $this->entityManager->persist($license);
        $this->entityManager->flush();

        if ($input->sendPaymentEmail) {
            $this->mailer->send($license);
        }
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
                throw new UseCaseException('Team not found', Response::HTTP_NOT_FOUND);
            }
            $teams[] = $team;
        }

        return $teams;
    }
}
