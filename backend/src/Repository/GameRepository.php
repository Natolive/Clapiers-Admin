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

    /**
     * @return Game[]
     */
    public function findUpcomingHomeGames(int $limit = 10): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.venue = :venue')
            ->andWhere('g.date >= :today')
            ->setParameter('venue', \App\Entity\Enum\GameVenue::HOME)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('g.date', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getStats(): array
    {
        $total = (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->getQuery()->getSingleScalarResult();

        $now = new \DateTimeImmutable();
        $seasonYear = (int) $now->format('n') >= 9 ? (int) $now->format('Y') : (int) $now->format('Y') - 1;
        $seasonStart = new \DateTimeImmutable($seasonYear . '-09-01');

        $thisSeason = (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.date >= :seasonStart')
            ->setParameter('seasonStart', $seasonStart)
            ->getQuery()->getSingleScalarResult();

        $upcoming = (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.date >= :today')
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()->getSingleScalarResult();

        return [
            'total' => $total,
            'thisSeason' => $thisSeason,
            'upcoming' => $upcoming,
        ];
    }

    public function countGamesByTeamAndDate(Team $team, \DateTimeImmutable $date, ?int $excludeGameId = null): int
    {
        $qb = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.team = :team')
            ->andWhere('g.date = :date')
            ->setParameter('team', $team)
            ->setParameter('date', $date);

        if ($excludeGameId !== null) {
            $qb->andWhere('g.id != :excludeId')
                ->setParameter('excludeId', $excludeGameId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countHomeGamesByDate(\DateTimeImmutable $date, ?int $excludeGameId = null): int
    {
        $qb = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.date = :date')
            ->andWhere('g.venue = :venue')
            ->setParameter('date', $date)
            ->setParameter('venue', \App\Entity\Enum\GameVenue::HOME);

        if ($excludeGameId !== null) {
            $qb->andWhere('g.id != :excludeId')
                ->setParameter('excludeId', $excludeGameId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
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
