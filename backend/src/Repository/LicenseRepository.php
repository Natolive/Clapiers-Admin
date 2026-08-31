<?php

namespace App\Repository;

use App\Entity\Enum\LicenseStatus;
use App\Entity\License;
use App\Entity\Member;
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

    public function findOneByMemberAndSeason(Member $member, string $season): ?License
    {
        return $this->findOneBy(['member' => $member, 'season' => $season]);
    }

    /**
     * @return License[]
     */
    public function findPaginated(int $page, int $limit, ?LicenseStatus $status = null, ?string $search = null, ?string $season = null): array
    {
        $page = max(1, $page);
        $limit = min(100, max(1, $limit));

        return $this->createFilteredQueryBuilder($status, $search, $season)
            ->orderBy('l.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByFilters(?LicenseStatus $status = null, ?string $search = null, ?string $season = null): int
    {
        return (int) $this->createFilteredQueryBuilder($status, $search, $season)
            ->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compteurs pour le tableau de bord, restreints à une saison : total + détail
     * par statut (toutes les valeurs de l'enum présentes, à 0 par défaut).
     *
     * @return array{total: int, byStatus: array<string, int>}
     */
    public function getStats(string $season): array
    {
        $byStatus = [];
        foreach (LicenseStatus::cases() as $case) {
            $byStatus[$case->value] = 0;
        }

        $rows = $this->createQueryBuilder('l')
            ->select('l.status AS status, COUNT(l.id) AS total')
            ->andWhere('l.season = :season')
            ->setParameter('season', $season)
            ->groupBy('l.status')
            ->getQuery()
            ->getScalarResult();

        $total = 0;
        foreach ($rows as $row) {
            $status = $row['status'] instanceof LicenseStatus ? $row['status']->value : (string) $row['status'];
            $count = (int) $row['total'];
            $byStatus[$status] = $count;
            $total += $count;
        }

        return ['total' => $total, 'byStatus' => $byStatus];
    }

    private function createFilteredQueryBuilder(?LicenseStatus $status, ?string $search, ?string $season = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('l')->join('l.member', 'm');

        if ($status !== null) {
            $qb->andWhere('l.status = :status')->setParameter('status', $status);
        }

        if ($season !== null && $season !== '') {
            $qb->andWhere('l.season = :season')->setParameter('season', $season);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere("LOWER(CONCAT(m.firstName, ' ', m.lastName)) LIKE :search OR LOWER(CONCAT(m.lastName, ' ', m.firstName)) LIKE :search")
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }

        return $qb;
    }
}
