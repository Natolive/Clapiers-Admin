<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Enum\LicenseStatus;
use App\Entity\Enum\MemberGender;
use App\Entity\License;
use App\Entity\Member;
use PHPUnit\Framework\TestCase;

class LicenseTest extends TestCase
{
    public function testToArrayExposesEveryField(): void
    {
        $member = (new Member())
            ->setFirstName('Marie')
            ->setLastName('Curie')
            ->setEmail('marie@test.fr')
            ->setPhoneNumber('+33612345678')
            ->setGender(MemberGender::FEMALE)
            ->setBirthDate(new \DateTimeImmutable('2000-05-01'))
            ->setNationality('Française');

        $license = (new License())
            ->setMember($member)
            ->setSeason('2026-2027')
            ->setStatus(LicenseStatus::VALIDEE)
            ->setAmount(12000)
            ->setHelloAssoTierId(7)
            ->setAccessToken('tok-123')
            ->setTokenExpiresAt(new \DateTimeImmutable('2026-09-30T12:00:00+00:00'))
            ->setMedicalCertificateFileName('cert.pdf')
            ->setLicenseNumber('LIC-42')
            ->setApprovedAt(new \DateTimeImmutable('2026-07-01T09:00:00+00:00'))
            ->setRejectionReason(null);

        $array = $license->toArray();

        $this->assertSame('2026-2027', $array['season']);
        $this->assertSame('validee', $array['status']);
        $this->assertSame(12000, $array['amount']);
        $this->assertSame(7, $array['helloAssoTierId']);
        $this->assertSame('tok-123', $array['accessToken']);
        $this->assertSame('cert.pdf', $array['medicalCertificateFileName']);
        $this->assertSame('LIC-42', $array['licenseNumber']);
        $this->assertNull($array['rejectionReason']);
        $this->assertSame('Marie', $array['member']['firstName']);
        $this->assertStringContainsString('2026-09-30', $array['tokenExpiresAt']);
        $this->assertStringContainsString('2026-07-01', $array['approvedAt']);
    }

    public function testRejectionReasonAndDefaults(): void
    {
        $license = (new License())->setRejectionReason('Certificat manquant');

        $this->assertSame('Certificat manquant', $license->getRejectionReason());
        $this->assertSame(LicenseStatus::SOUMISE, $license->getStatus());
        $this->assertNull($license->getAmount());
    }
}
