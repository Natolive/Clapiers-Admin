<?php

namespace App\Repository;

use App\Entity\Season;
use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Team>
 */
class TeamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Team::class);
    }

    /**
     * @return Team[]
     */
    public function findBySeason(Season $season): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.season = :season')
            ->setParameter('season', $season)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countBySeason(Season $season): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.season = :season')
            ->setParameter('season', $season)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
