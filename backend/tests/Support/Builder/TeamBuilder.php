<?php

namespace App\Tests\Support\Builder;

use App\Entity\Team;
use Doctrine\ORM\EntityManagerInterface;

final class TeamBuilder
{
    private static int $seq = 0;

    private ?string $name = null;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function named(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function build(): Team
    {
        $team = new Team();
        $team->setName($this->name ?? sprintf('Équipe %03d', ++self::$seq));

        return $team;
    }

    public function persist(): Team
    {
        $team = $this->build();
        $this->em->persist($team);
        $this->em->flush();

        return $team;
    }
}
