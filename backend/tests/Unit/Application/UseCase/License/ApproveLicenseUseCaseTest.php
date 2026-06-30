<?php

namespace App\Tests\Unit\Application\UseCase\License;

use App\Application\UseCase\License\ApproveLicense\ApproveLicenseCommand;
use App\Application\UseCase\License\ApproveLicense\ApproveLicenseUseCase;
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

class ApproveLicenseUseCaseTest extends TestCase
{
    public function testApprovalGeneratesTokenAndSurvivesMailerOutage(): void
    {
        $member = (new Member())
            ->setFirstName('Marie')
            ->setLastName('Curie')
            ->setEmail('marie@test.fr');

        // Pas de token au départ : l'approbation doit en générer un.
        $license = (new License())->setMember($member)->setSeason('2026-2027');
        $this->assertNull($license->getAccessToken());

        $repository = $this->createStub(LicenseRepository::class);
        $repository->method('find')->willReturn($license);

        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willThrowException(new TransportException('SMTP down'));

        $useCase = new ApproveLicenseUseCase(
            $repository,
            $this->createMock(EntityManagerInterface::class),
            $mailer,
            new NullLogger(),
            'club@test.fr',
            'http://localhost:3000',
        );

        $result = $useCase->run(new ApproveLicenseCommand(1, 102, 12000));

        $this->assertSame(LicenseStatus::VALIDEE, $result->getStatus());
        $this->assertSame(12000, $result->getAmount());
        $this->assertNotNull($result->getAccessToken());
        $this->assertSame(MemberStatus::ACTIVE, $result->getMember()->getStatus());
    }
}
