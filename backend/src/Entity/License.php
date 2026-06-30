<?php

namespace App\Entity;

use App\Entity\Enum\LicenseStatus;
use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampableTrait;
use App\Repository\LicenseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LicenseRepository::class)]
#[ORM\HasLifecycleCallbacks]
class License
{
    use IdTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: Member::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Member $member;

    #[ORM\Column(length: 9)]
    private string $season;

    #[ORM\Column(length: 20, enumType: LicenseStatus::class)]
    private LicenseStatus $status = LicenseStatus::SOUMISE;

    /** Montant en centimes, figé à la validation (null tant que non validé). */
    #[ORM\Column(nullable: true)]
    private ?int $amount = null;

    /** Tier HelloAsso retenu par l'admin à la validation. */
    #[ORM\Column(nullable: true)]
    private ?int $helloAssoTierId = null;

    /** Jeton opaque du magic link (upload de pièces puis paiement). */
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $accessToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $tokenExpiresAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $medicalCertificateFileName = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $licenseNumber = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

    public function getMember(): Member
    {
        return $this->member;
    }

    public function setMember(Member $member): static
    {
        $this->member = $member;

        return $this;
    }

    public function getSeason(): string
    {
        return $this->season;
    }

    public function setSeason(string $season): static
    {
        $this->season = $season;

        return $this;
    }

    public function getStatus(): LicenseStatus
    {
        return $this->status;
    }

    public function setStatus(LicenseStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(?int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getHelloAssoTierId(): ?int
    {
        return $this->helloAssoTierId;
    }

    public function setHelloAssoTierId(?int $helloAssoTierId): static
    {
        $this->helloAssoTierId = $helloAssoTierId;

        return $this;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(?string $accessToken): static
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function getTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->tokenExpiresAt;
    }

    public function setTokenExpiresAt(?\DateTimeImmutable $tokenExpiresAt): static
    {
        $this->tokenExpiresAt = $tokenExpiresAt;

        return $this;
    }

    public function getMedicalCertificateFileName(): ?string
    {
        return $this->medicalCertificateFileName;
    }

    public function setMedicalCertificateFileName(?string $fileName): static
    {
        $this->medicalCertificateFileName = $fileName;

        return $this;
    }

    public function getLicenseNumber(): ?string
    {
        return $this->licenseNumber;
    }

    public function setLicenseNumber(?string $licenseNumber): static
    {
        $this->licenseNumber = $licenseNumber;

        return $this;
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTimeImmutable $approvedAt): static
    {
        $this->approvedAt = $approvedAt;

        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'member' => $this->getMember()->toArray(),
            'season' => $this->getSeason(),
            'status' => $this->getStatus()->value,
            'amount' => $this->getAmount(),
            'helloAssoTierId' => $this->getHelloAssoTierId(),
            'accessToken' => $this->getAccessToken(),
            'tokenExpiresAt' => $this->getTokenExpiresAt()?->format(DATE_ATOM),
            'medicalCertificateFileName' => $this->getMedicalCertificateFileName(),
            'licenseNumber' => $this->getLicenseNumber(),
            'approvedAt' => $this->getApprovedAt()?->format(DATE_ATOM),
            'rejectionReason' => $this->getRejectionReason(),
            'createdAt' => $this->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $this->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }
}
