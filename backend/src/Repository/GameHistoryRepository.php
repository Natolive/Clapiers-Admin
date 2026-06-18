<?php

namespace App\Repository;

use App\Entity\GameHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameHistory>
 */
class GameHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameHistory::class);
    }

    /**
     * @return GameHistory[]
     */
    public function findPaginated(int $page, int $limit, ?int $gameId = null, ?int $teamId = null): array
    {
        $page  = max(1, $page);
        $limit = min(100, max(1, $limit));

        return $this->createFilteredQueryBuilder($gameId, $teamId)
            ->orderBy('h.createdAt', 'DESC')
            ->addOrderBy('h.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countFiltered(?int $gameId = null, ?int $teamId = null): int
    {
        return (int) $this->createFilteredQueryBuilder($gameId, $teamId)
            ->select('COUNT(h.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function createFilteredQueryBuilder(?int $gameId, ?int $teamId): QueryBuilder
    {
        $qb = $this->createQueryBuilder('h');

        if ($gameId !== null) {
            $qb->andWhere('h.gameId = :gameId')->setParameter('gameId', $gameId);
        }

        if ($teamId !== null) {
            $qb->andWhere('h.teamId = :teamId')->setParameter('teamId', $teamId);
        }

        return $qb;
    }
}
