<?php

namespace App\Repository;

use App\Entity\Log;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Log>
 */
class LogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Log::class);
    }

    /**
     * @return Log[]
     */
    public function findPaginated(int $page, int $limit, ?string $level = null, ?string $search = null): array
    {
        $page = max(1, $page);
        $limit = min(200, max(1, $limit));

        return $this->createFilteredQueryBuilder($level, $search)
            ->orderBy('l.createdAt', 'DESC')
            ->addOrderBy('l.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByFilters(?string $level = null, ?string $search = null): int
    {
        return (int) $this->createFilteredQueryBuilder($level, $search)
            ->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Purge complète des logs (bouton « Vider » de la page admin). */
    public function deleteAll(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->delete()
            ->getQuery()
            ->execute();
    }

    /** Supprime les logs antérieurs au seuil (rétention). Renvoie le nombre supprimé. */
    public function deleteOlderThan(\DateTimeImmutable $threshold): int
    {
        return (int) $this->createQueryBuilder('l')
            ->delete()
            ->where('l.createdAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }

    private function createFilteredQueryBuilder(?string $level, ?string $search): QueryBuilder
    {
        $qb = $this->createQueryBuilder('l');

        if ($level !== null && $level !== '') {
            $qb->andWhere('l.level = :level')->setParameter('level', $level);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('LOWER(l.message) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }

        return $qb;
    }
}
