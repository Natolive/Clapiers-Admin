<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampableTrait;
use App\Repository\SeasonsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeasonsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Seasons
{
    use IdTrait;
    use TimestampableTrait;

    #[ORM\Column]
    private int $startYear;

    #[ORM\Column]
    private int $endYear;

    public function getStartYear(): int
    {
        return $this->startYear;
    }

    public function setStartYear(int $startYear): static
    {
        $this->startYear = $startYear;

        return $this;
    }

    public function getEndYear(): int
    {
        return $this->endYear;
    }

    public function setEndYear(int $endYear): static
    {
        $this->endYear = $endYear;

        return $this;
    }

    public function isActual(): bool
    {
        $now = new \DateTimeImmutable('now');
        $seasonStart = new \DateTimeImmutable($this->startYear . '-08-01');
        $seasonEnd = new \DateTimeImmutable($this->endYear . '-07-31 23:59:59');

        return $now >= $seasonStart && $now <= $seasonEnd;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'startYear' => $this->getStartYear(),
            'endYear' => $this->getEndYear(),
            'isActual' => $this->isActual(),
            'createdAt' => $this->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $this->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }
}
