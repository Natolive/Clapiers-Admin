<?php

namespace App\Tests\Unit\Application\UseCase\License;

use App\Application\UseCase\License\SubmitLicenseRequest\SubmitLicenseRequestCommand;
use App\Application\UseCase\License\SubmitLicenseRequest\SubmitLicenseRequestUseCase;
use App\Common\Exception\UseCaseException;
use App\Common\Service\RecaptchaVerifier;
use App\Common\Service\SeasonProvider;
use App\Common\Service\SeasonResolver;
use App\Repository\SettingRepository;
use App\Entity\Enum\MemberGender;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class SubmitLicenseRequestUseCaseTest extends TestCase
{
    public function testRejectedCaptchaBlocksTheRequest(): void
    {
        $verifier = $this->createStub(RecaptchaVerifier::class);
        $verifier->method('verify')->willReturn(false);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $useCase = $this->makeUseCase($verifier, $entityManager);

        $this->expectException(UseCaseException::class);
        $this->expectExceptionMessage('Veuillez valider le captcha.');

        $useCase->run($this->command());
    }

    public function testInvalidBirthDateIsRejected(): void
    {
        $verifier = $this->createStub(RecaptchaVerifier::class);
        $verifier->method('verify')->willReturn(true);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $useCase = $this->makeUseCase($verifier, $entityManager);

        $this->expectException(UseCaseException::class);
        $this->expectExceptionMessage('Date de naissance invalide.');

        $useCase->run($this->command(birthDate: 'pas-une-date'));
    }

    private function makeUseCase(RecaptchaVerifier $verifier, EntityManagerInterface $entityManager): SubmitLicenseRequestUseCase
    {
        $seasonProvider = new SeasonProvider($this->createStub(SettingRepository::class), new SeasonResolver());

        return new SubmitLicenseRequestUseCase($entityManager, $verifier, $seasonProvider);
    }

    private function command(string $birthDate = '2000-05-01'): SubmitLicenseRequestCommand
    {
        return new SubmitLicenseRequestCommand(
            firstName: 'Marie',
            lastName: 'Curie',
            phoneNumber: '+33612345678',
            email: 'marie@test.fr',
            addressStreet: '1 rue X',
            addressZip: '34000',
            addressCity: 'Montpellier',
            gender: MemberGender::FEMALE,
            birthDate: $birthDate,
            nationality: 'Française',
            recaptchaToken: 'token',
        );
    }
}
