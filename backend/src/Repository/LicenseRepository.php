<?php

namespace App\Repository;

use App\Entity\Enum\LicenseStatus;
use App\Entity\License;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<License>
 */
class LicenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, License::class);
    }

    public function findOneByAccessToken(string $token): ?License
    {
        return $this->findOneBy(['accessToken' => $token]);
    }

    /**
     * @return License[]
     */
    public function findPaginated(int $page, int $limit, ?LicenseStatus $status = null, ?string $search = null): array
    {
        $page = max(1, $page);
        $limit = min(100, max(1, $limit));

        return $this->createFilteredQueryBuilder($status, $search)
            ->orderBy('l.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByFilters(?LicenseStatus $status = null, ?string $search = null): int
    {
        return (int) $this->createFilteredQueryBuilder($status, $search)
            ->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function createFilteredQueryBuilder(?LicenseStatus $status, ?string $search): QueryBuilder
    {
        $qb = $this->createQueryBuilder('l')->join('l.member', 'm');

        if ($status !== null) {
            $qb->andWhere('l.status = :status')->setParameter('status', $status);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere("LOWER(CONCAT(m.firstName, ' ', m.lastName)) LIKE :search OR LOWER(CONCAT(m.lastName, ' ', m.firstName)) LIKE :search")
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }

        return $qb;
    }
}
