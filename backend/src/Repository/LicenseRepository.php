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
     * Licence de la saison de chaque membre, indexée par id de membre — une
     * requête pour toute la liste, au lieu d'une par membre à l'export.
     * Un membre n'a qu'une licence par saison ; si l'historique en contenait
     * plusieurs, la plus récente gagne.
     *
     * @param list<int> $memberIds
     *
     * @return array<int, License>
     */
    public function findBySeasonIndexedByMember(array $memberIds, string $season): array
    {
        if ($memberIds === []) {
            return [];
        }

        $licenses = $this->createQueryBuilder('l')
            ->andWhere('l.member IN (:memberIds)')
            ->andWhere('l.season = :season')
            ->setParameter('memberIds', $memberIds)
            ->setParameter('season', $season)
            ->orderBy('l.id', 'ASC')
            ->getQuery()
            ->getResult();

        $byMember = [];
        foreach ($licenses as $license) {
            $byMember[$license->getMember()->getId()] = $license;
        }

        return $byMember;
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
     * par statut (toutes les valeurs de l'enum présentes, à 0 par défaut) +
     * montant encaissé (somme des licences payées, en centimes).
     *
     * @return array{total: int, byStatus: array<string, int>, paidAmount: int}
     */
    public function getStats(string $season): array
    {
        $byStatus = [];
        foreach (LicenseStatus::cases() as $case) {
            $byStatus[$case->value] = 0;
        }

        $rows = $this->createQueryBuilder('l')
            ->select('l.status AS status, COUNT(l.id) AS total, SUM(l.amount) AS amount')
            ->andWhere('l.season = :season')
            ->setParameter('season', $season)
            ->groupBy('l.status')
            ->getQuery()
            ->getScalarResult();

        $total = 0;
        $paidAmount = 0;
        foreach ($rows as $row) {
            $status = $row['status'] instanceof LicenseStatus ? $row['status']->value : (string) $row['status'];
            $count = (int) $row['total'];
            $byStatus[$status] = $count;
            $total += $count;

            if ($status === LicenseStatus::PAYEE->value) {
                $paidAmount = (int) $row['amount'];
            }
        }

        return ['total' => $total, 'byStatus' => $byStatus, 'paidAmount' => $paidAmount];
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

        MemberSearchFilter::apply($qb, 'm', $search);

        return $qb;
    }
}
