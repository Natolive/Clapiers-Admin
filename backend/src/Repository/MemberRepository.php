<?php

namespace App\Repository;

use App\Entity\Member;
use App\Entity\Season;
use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Member>
 */
class MemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Member::class);
    }

    /**
     * @return Member[]
     */
    public function findByTeam(Team $team): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.team = :team')
            ->setParameter('team', $team)
            ->orderBy('m.lastName', 'ASC')
            ->addOrderBy('m.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countBySeason(Season $season): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->join('m.team', 't')
            ->andWhere('t.season = :season')
            ->setParameter('season', $season)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
