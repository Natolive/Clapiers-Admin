<?php

namespace App\Tests\Support\Builder;

use App\Entity\SalleClosure;
use Doctrine\ORM\EntityManagerInterface;

final class SalleClosureBuilder
{
    private \DateTimeImmutable $startDate;
    private \DateTimeImmutable $endDate;
    private ?string $reason = 'Vacances';

    public function __construct(private readonly EntityManagerInterface $em)
    {
        $this->startDate = new \DateTimeImmutable('today');
        $this->endDate = new \DateTimeImmutable('today');
    }

    public function from(string|\DateTimeImmutable $date): self
    {
        $this->startDate = is_string($date) ? new \DateTimeImmutable($date) : $date;

        return $this;
    }

    public function to(string|\DateTimeImmutable $date): self
    {
        $this->endDate = is_string($date) ? new \DateTimeImmutable($date) : $date;

        return $this;
    }

    public function because(?string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    public function build(): SalleClosure
    {
        $closure = new SalleClosure();
        $closure->setStartDate($this->startDate);
        $closure->setEndDate($this->endDate);
        $closure->setReason($this->reason);

        return $closure;
    }

    public function persist(): SalleClosure
    {
        $closure = $this->build();
        $this->em->persist($closure);
        $this->em->flush();

        return $closure;
    }
}
