<?php

namespace App\Tests\Unit\Application\UseCase\License;

use App\Application\UseCase\License\RejectLicense\RejectLicenseCommand;
use App\Application\UseCase\License\RejectLicense\RejectLicenseUseCase;
use App\Entity\Enum\LicenseStatus;
use App\Entity\Enum\MemberStatus;
use App\Entity\License;
use App\Entity\Member;
use App\Repository\LicenseRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;

class RejectLicenseUseCaseTest extends TestCase
{
    public function testRejectionSurvivesMailerOutage(): void
    {
        $member = (new Member())
            ->setFirstName('Marie')
            ->setLastName('Curie')
            ->setEmail('marie@test.fr');

        $license = (new License())->setMember($member)->setSeason('2026-2027');

        $repository = $this->createStub(LicenseRepository::class);
        $repository->method('find')->willReturn($license);

        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willThrowException(new TransportException('SMTP down'));

        $useCase = new RejectLicenseUseCase(
            $repository,
            $this->createMock(EntityManagerInterface::class),
            $mailer,
            new NullLogger(),
            'club@test.fr',
        );

        $result = $useCase->run(new RejectLicenseCommand(1, 'Certificat manquant'));

        $this->assertSame(LicenseStatus::REFUSEE, $result->getStatus());
        $this->assertSame('Certificat manquant', $result->getRejectionReason());
        $this->assertSame(MemberStatus::REJECTED, $result->getMember()->getStatus());
    }
}
