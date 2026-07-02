<?php

namespace App\Application\UseCase\License\SubmitLicenseRequest;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\RecaptchaVerifier;
use App\Common\Service\SeasonProvider;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Enum\LicenseStatus;
use App\Entity\Enum\MemberStatus;
use App\Entity\License;
use App\Entity\Member;
use App\Entity\ValueObject\Address;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends AbstractUseCase<SubmitLicenseRequestCommand>
 */
class SubmitLicenseRequestUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RecaptchaVerifier $recaptchaVerifier,
        private readonly SeasonProvider $seasonProvider,
    ) {
    }

    public function run(?CommandInterface $command = null): License
    {
        if (!$command instanceof SubmitLicenseRequestCommand) {
            throw new UseCaseException('Invalid command');
        }

        if (!$this->recaptchaVerifier->verify($command->recaptchaToken)) {
            throw new UseCaseException('Veuillez valider le captcha.');
        }

        $member = new Member();
        $member->setFirstName($command->firstName);
        $member->setLastName($command->lastName);
        $member->setPhoneNumber($command->phoneNumber);
        $member->setEmail($command->email);
        $member->setAddress(new Address($command->addressStreet, $command->addressZip, $command->addressCity));
        $member->setGender($command->gender);
        $member->setBirthDate($this->parseBirthDate($command->birthDate));
        $member->setNationality($command->nationality);
        $member->setLicenseNumber($command->licenseNumber);
        $member->setStatus(MemberStatus::PENDING_VALIDATION);

        $license = new License();
        $license->setMember($member);
        $license->setSeason($this->seasonProvider->current());
        $license->setStatus(LicenseStatus::SOUMISE);
        $license->setLicenseNumber($command->licenseNumber);
        $license->setAccessToken(bin2hex(random_bytes(32)));

        $this->entityManager->persist($member);
        $this->entityManager->persist($license);
        $this->entityManager->flush();

        return $license;
    }

    private function parseBirthDate(string $birthDate): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($birthDate);
        } catch (\Exception) {
            throw new UseCaseException('Date de naissance invalide.', 422);
        }
    }
}
