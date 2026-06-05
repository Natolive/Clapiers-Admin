<?php

namespace App\Repository;

use App\Entity\ContactMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactMessage>
 */
class ContactMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactMessage::class);
    }

    /**
     * @return ContactMessage[]
     */
    public function findPaginatedOrderedByDate(int $page, int $limit, ?string $search = null): array
    {
        $page = max(1, $page);
        $limit = min(100, max(1, $limit));

        return $this->createSearchQueryBuilder($search)
            ->orderBy('m.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countBySearch(?string $search = null): int
    {
        return (int) $this->createSearchQueryBuilder($search)
            ->select('COUNT(m.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function createSearchQueryBuilder(?string $search): QueryBuilder
    {
        $qb = $this->createQueryBuilder('m');

        if ($search !== null && $search !== '') {
            $qb->andWhere("LOWER(CONCAT(m.firstName, ' ', m.lastName)) LIKE :search OR LOWER(CONCAT(m.lastName, ' ', m.firstName)) LIKE :search")
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }

        return $qb;
    }
}
