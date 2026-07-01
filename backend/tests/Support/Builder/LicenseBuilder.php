<?php

namespace App\Tests\Support\Builder;

use App\Entity\Enum\LicenseStatus;
use App\Entity\Enum\MemberStatus;
use App\Entity\License;
use App\Entity\Member;
use Doctrine\ORM\EntityManagerInterface;

final class LicenseBuilder
{
    private static int $seq = 0;

    private ?Member $member = null;
    private string $season = '2026-2027';
    private LicenseStatus $status = LicenseStatus::SOUMISE;
    private ?string $accessToken = null;
    private ?string $medicalCertificateFileName = null;
    private ?int $amount = null;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function forMember(Member $member): self
    {
        $this->member = $member;

        return $this;
    }

    public function withToken(string $token): self
    {
        $this->accessToken = $token;

        return $this;
    }

    public function withStatus(LicenseStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function withMedicalCertificate(string $fileName): self
    {
        $this->medicalCertificateFileName = $fileName;

        return $this;
    }

    public function withAmount(?int $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function build(): License
    {
        $n = ++self::$seq;

        $member = $this->member ?? (new MemberBuilder($this->em))->build();
        $member->setStatus(MemberStatus::PENDING_VALIDATION);

        $license = new License();
        $license->setMember($member);
        $license->setSeason($this->season);
        $license->setStatus($this->status);
        $license->setAccessToken($this->accessToken ?? sprintf('token-%03d-%s', $n, bin2hex(random_bytes(4))));
        $license->setMedicalCertificateFileName($this->medicalCertificateFileName);
        $license->setAmount($this->amount);

        return $license;
    }

    public function persist(): License
    {
        $license = $this->build();
        $this->em->persist($license->getMember());
        $this->em->persist($license);
        $this->em->flush();

        return $license;
    }
}
