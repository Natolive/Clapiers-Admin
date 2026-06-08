<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampableTrait;
use App\Repository\SalleClosureRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A period during which the gym ("salle") is closed (holidays, works, ...).
 * A single-day closure has startDate == endDate. Range is inclusive on both ends.
 */
#[ORM\Entity(repositoryClass: SalleClosureRepository::class)]
#[ORM\HasLifecycleCallbacks]
class SalleClosure
{
    use IdTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $startDate;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $endDate;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reason = null;

    public function getStartDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id'        => $this->getId(),
            'startDate' => $this->getStartDate()->format('Y-m-d'),
            'endDate'   => $this->getEndDate()->format('Y-m-d'),
            'reason'    => $this->getReason(),
            'createdAt' => $this->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $this->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }
}
