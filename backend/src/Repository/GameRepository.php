<?php

namespace App\Repository;

use App\Entity\Game;
use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game>
 */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    /**
     * @return Game[]
     */
    public function findByTeamAndDateRange(Team $team, ?string $start, ?string $end): array
    {
        $qb = $this->createQueryBuilder('g')
            ->andWhere('g.team = :team')
            ->setParameter('team', $team)
            ->orderBy('g.date', 'ASC');

        $this->applyDateRange($qb, $start, $end);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Game[]
     */
    public function findAllByDateRange(?string $start, ?string $end): array
    {
        $qb = $this->createQueryBuilder('g')
            ->orderBy('g.date', 'ASC');

        $this->applyDateRange($qb, $start, $end);

        return $qb->getQuery()->getResult();
    }

    private function applyDateRange(\Doctrine\ORM\QueryBuilder $qb, ?string $start, ?string $end): void
    {
        if ($start) {
            $qb->andWhere('g.date >= :start')
                ->setParameter('start', new \DateTimeImmutable($start));
        }

        if ($end) {
            $qb->andWhere('g.date <= :end')
                ->setParameter('end', new \DateTimeImmutable($end));
        }
    }
}
