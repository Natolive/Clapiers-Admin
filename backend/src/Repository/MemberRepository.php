<?php

namespace App\Repository;

use App\Entity\Member;
use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
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
     * @return array{data: Member[], total: int}
     */
    public function findPaginated(
        int $page,
        int $limit,
        string $sortField,
        string $sortOrder,
        ?string $search = null,
        ?int $teamId = null,
        ?bool $licensePaid = null,
        ?bool $hasLicense = null,
    ): array {
        $allowedFields = [
            'firstName' => 'm.firstName',
            'lastName' => 'm.lastName',
            'email' => 'm.email',
            'phoneNumber' => 'm.phoneNumber',
            'createdAt' => 'm.createdAt',
            'licensePaid' => 'm.licensePaid',
        ];

        $orderColumn = $allowedFields[$sortField] ?? 'm.firstName';
        $orderDir = strtolower($sortOrder) === 'desc' ? 'DESC' : 'ASC';

        $qb = $this->createQueryBuilder('m');

        if ($search) {
            $searchTerm = '%' . $search . '%';
            $qb->andWhere('LOWER(m.firstName) LIKE LOWER(:search) OR LOWER(m.lastName) LIKE LOWER(:search) OR LOWER(m.email) LIKE LOWER(:search) OR m.phoneNumber LIKE :search')
                ->setParameter('search', $searchTerm);
        }

        if ($teamId) {
            // ManyToMany : un membre matche au plus une fois pour un teamId donné, pas de doublon
            $qb->innerJoin('m.teams', 't')
                ->andWhere('t.id = :teamId')
                ->setParameter('teamId', $teamId);
        }

        if ($licensePaid !== null) {
            $qb->andWhere('m.licensePaid = :licensePaid')
                ->setParameter('licensePaid', $licensePaid);
        }

        if ($hasLicense !== null) {
            if ($hasLicense) {
                $qb->andWhere('m.licenseFileName IS NOT NULL');
            } else {
                $qb->andWhere('m.licenseFileName IS NULL');
            }
        }

        $total = (clone $qb)
            ->select('COUNT(m.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $qb->orderBy($orderColumn, $orderDir);

        if ($orderColumn !== 'm.firstName') {
            $qb->addOrderBy('m.firstName', 'ASC');
        }
        if ($orderColumn !== 'm.lastName') {
            $qb->addOrderBy('m.lastName', 'ASC');
        }

        // Fetch join APRÈS le COUNT cloné ; le Paginator pagine correctement
        // malgré la collection jointe (sinon setMaxResults tronque les lignes
        // SQL, pas les membres) et évite une requête teams par membre sérialisé.
        $qb->leftJoin('m.teams', 'allTeams')
            ->addSelect('allTeams')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $data = iterator_to_array(new Paginator($qb->getQuery()));

        return ['data' => $data, 'total' => (int) $total];
    }

    public function getStats(): array
    {
        $total = (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->getQuery()->getSingleScalarResult();

        $withLicense = (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.licensePaid = true')
            ->getQuery()->getSingleScalarResult();

        $conn = $this->getEntityManager()->getConnection();

        // Répartition par sexe
        $byGenderRaw = $conn->fetchAllAssociative(
            'SELECT gender, COUNT(*) AS total FROM member GROUP BY gender'
        );
        $byGender = ['male' => 0, 'female' => 0, 'other' => 0];
        foreach ($byGenderRaw as $row) {
            $byGender[$row['gender']] = (int) $row['total'];
        }

        // Stats d'âge
        $ageStats = $conn->fetchAssociative(
            "SELECT
                ROUND(AVG(EXTRACT(YEAR FROM AGE(birth_date)))::numeric, 1) AS avg_age,
                MIN(EXTRACT(YEAR FROM AGE(birth_date)))                    AS min_age,
                MAX(EXTRACT(YEAR FROM AGE(birth_date)))                    AS max_age,
                COUNT(CASE WHEN EXTRACT(YEAR FROM AGE(birth_date)) < 10              THEN 1 END) AS under10,
                COUNT(CASE WHEN EXTRACT(YEAR FROM AGE(birth_date)) BETWEEN 10 AND 17 THEN 1 END) AS age10_17,
                COUNT(CASE WHEN EXTRACT(YEAR FROM AGE(birth_date)) BETWEEN 18 AND 25 THEN 1 END) AS age18_25,
                COUNT(CASE WHEN EXTRACT(YEAR FROM AGE(birth_date)) BETWEEN 26 AND 35 THEN 1 END) AS age26_35,
                COUNT(CASE WHEN EXTRACT(YEAR FROM AGE(birth_date)) BETWEEN 36 AND 45 THEN 1 END) AS age36_45,
                COUNT(CASE WHEN EXTRACT(YEAR FROM AGE(birth_date)) > 45              THEN 1 END) AS over45
            FROM member WHERE birth_date IS NOT NULL"
        );

        // Inscriptions par mois (12 derniers mois)
        $byMonthRaw = $conn->fetchAllAssociative(
            "SELECT TO_CHAR(created_at, 'YYYY-MM') AS month, COUNT(*) AS total
             FROM member
             WHERE created_at >= NOW() - INTERVAL '12 months'
             GROUP BY month ORDER BY month ASC"
        );

        // Inscrits ce mois / cette année
        $now = new \DateTimeImmutable();
        $newThisMonth = (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.createdAt >= :from')
            ->setParameter('from', new \DateTimeImmutable($now->format('Y-m') . '-01'))
            ->getQuery()->getSingleScalarResult();

        $newThisYear = (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.createdAt >= :from')
            ->setParameter('from', new \DateTimeImmutable($now->format('Y') . '-01-01'))
            ->getQuery()->getSingleScalarResult();

        return [
            'total'          => $total,
            'withLicense'    => $withLicense,
            'withoutLicense' => $total - $withLicense,
            'byGender'       => $byGender,
            'age'            => [
                'average' => (float) ($ageStats['avg_age'] ?? 0),
                'min'     => (int)   ($ageStats['min_age'] ?? 0),
                'max'     => (int)   ($ageStats['max_age'] ?? 0),
                'byRange' => [
                    'Moins de 10 ans' => (int) ($ageStats['under10']  ?? 0),
                    '10-17 ans'       => (int) ($ageStats['age10_17'] ?? 0),
                    '18-25 ans'       => (int) ($ageStats['age18_25'] ?? 0),
                    '26-35 ans'       => (int) ($ageStats['age26_35'] ?? 0),
                    '36-45 ans'       => (int) ($ageStats['age36_45'] ?? 0),
                    'Plus de 45 ans'  => (int) ($ageStats['over45']   ?? 0),
                ],
            ],
            'createdAt' => [
                'newThisMonth' => $newThisMonth,
                'newThisYear'  => $newThisYear,
                'byMonth'      => array_map(fn($r) => [
                    'month' => $r['month'],
                    'total' => (int) $r['total'],
                ], $byMonthRaw),
            ],
        ];
    }

    /**
     * @return Member[]
     */
    public function findByTeam(Team $team): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.teams', 't')
            ->addSelect('t')
            ->andWhere(':team MEMBER OF m.teams')
            ->setParameter('team', $team)
            ->orderBy('m.lastName', 'ASC')
            ->addOrderBy('m.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Member[]
     */
    public function findAllWithTeams(): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.teams', 't')
            ->addSelect('t')
            ->orderBy('m.lastName', 'ASC')
            ->addOrderBy('m.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
