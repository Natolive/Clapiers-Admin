<?php

namespace App\Repository;

use App\Entity\Enum\LicenseStatus;
use App\Entity\Enum\MemberStatus;
use App\Entity\License;
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
        ?string $season = null,
    ): array {
        $allowedFields = [
            'firstName' => 'm.firstName',
            'lastName' => 'm.lastName',
            'email' => 'm.email',
            'phoneNumber' => 'm.phoneNumber',
            'createdAt' => 'm.createdAt',
        ];

        $orderColumn = $allowedFields[$sortField] ?? 'm.firstName';
        $orderDir = strtolower($sortOrder) === 'desc' ? 'DESC' : 'ASC';

        $qb = $this->createQueryBuilder('m');

        // La liste des licenciés n'affiche que les membres actifs : les demandes
        // en attente de validation ou refusées restent dans "Demandes de licence".
        $qb->andWhere('m.status = :activeStatus')
            ->setParameter('activeStatus', MemberStatus::ACTIVE);

        // Scopée à la saison courante (même population que le dashboard) : le membre
        // doit avoir une licence validée pour cette saison. Exclut les licenciés
        // d'une saison passée non renouvelés et les créations sans licence.
        if ($season !== null) {
            $qb->andWhere('EXISTS (SELECT ls.id FROM '.License::class.' ls WHERE ls.member = m AND ls.season = :season AND ls.status IN (:validatedStatuses))')
                ->setParameter('season', $season)
                ->setParameter('validatedStatuses', LicenseStatus::activeMembership());
        }

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
            // "Payé" est dérivé : le membre a (ou non) une licence PAYEE. Si une
            // saison est fournie, on la restreint à cette saison (cohérence UI).
            $seasonClause = $season !== null ? ' AND lp.season = :season' : '';
            $exists = 'EXISTS (SELECT lp.id FROM '.License::class.' lp WHERE lp.member = m AND lp.status = :paidStatus'.$seasonClause.')';
            $qb->andWhere($licensePaid ? $exists : 'NOT '.$exists)
                ->setParameter('paidStatus', LicenseStatus::PAYEE);
            if ($season !== null) {
                $qb->setParameter('season', $season);
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

    /**
     * Statistiques du tableau de bord, restreintes à la population de la saison
     * courante : un membre ne compte que s'il a une licence VALIDÉE pour cette
     * saison (soit validée, en paiement ou payée). Les demandes soumises/refusées
     * ne sont pas des membres. « Licence payée » = statut payée ; « sans licence »
     * = validé mais pas encore payé.
     */
    public function getStats(string $season): array
    {
        $validated = LicenseStatus::activeMembership();

        // Population (DQL) : membre avec une licence validée pour la saison.
        $pop = 'EXISTS (SELECT lp.id FROM '.License::class.' lp'
            .' WHERE lp.member = m AND lp.season = :season AND lp.status IN (:validated))';

        $total = (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere($pop)
            ->setParameter('season', $season)
            ->setParameter('validated', $validated)
            ->getQuery()->getSingleScalarResult();

        // Licence payée = validée puis payée (statut payée) sur la saison.
        $withLicense = (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('EXISTS (SELECT lp.id FROM '.License::class.' lp WHERE lp.member = m AND lp.season = :season AND lp.status = :paidStatus)')
            ->setParameter('season', $season)
            ->setParameter('paidStatus', LicenseStatus::PAYEE)
            ->getQuery()->getSingleScalarResult();

        $conn = $this->getEntityManager()->getConnection();

        // Bornes de la saison sportive (bascule en septembre, cf. SeasonResolver) :
        // "2026-2027" → [2026-09-01, 2027-09-01). Les métriques d'inscription sont
        // fenêtrées sur la saison affichée, pas sur la date du jour.
        $startYear = (int) explode('-', $season)[0];
        $seasonStart = sprintf('%d-09-01', $startYear);
        $seasonEnd = sprintf('%d-09-01', $startYear + 1);

        // Même population, en SQL brut, pour les agrégats ci-dessous. Valeurs
        // issues de l'enum (aucune saisie externe) → interpolation sûre.
        $statusList = implode(', ', array_map(
            static fn (string $v) => "'".$v."'",
            LicenseStatus::activeMembershipValues()
        ));
        // `member.deleted_at IS NULL` en dur : le filtre Doctrine `softdeleteable`
        // ne s'applique qu'au DQL. Sans lui, les trois agrégats bruts ci-dessous
        // continueraient de compter les licenciés supprimés, alors que le total
        // (DQL) les exclut — tableau de bord incohérent.
        $popSql = "member.deleted_at IS NULL"
            ." AND EXISTS (SELECT 1 FROM license l WHERE l.member_id = member.id"
            ." AND l.deleted_at IS NULL"
            ." AND l.season = :season AND l.status IN ($statusList))";

        // Répartition par sexe
        $byGenderRaw = $conn->fetchAllAssociative(
            "SELECT gender, COUNT(*) AS total FROM member WHERE $popSql GROUP BY gender",
            ['season' => $season]
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
            FROM member WHERE birth_date IS NOT NULL AND $popSql",
            ['season' => $season]
        );

        // Inscriptions par mois, sur les 12 mois de la saison (par date de création).
        $byMonthRaw = $conn->fetchAllAssociative(
            "SELECT TO_CHAR(created_at, 'YYYY-MM') AS month, COUNT(*) AS total
             FROM member
             WHERE created_at >= :seasonStart AND created_at < :seasonEnd AND $popSql
             GROUP BY month ORDER BY month ASC",
            ['season' => $season, 'seasonStart' => $seasonStart, 'seasonEnd' => $seasonEnd]
        );

        // Nouveaux membres = première adhésion cette saison : dans la population
        // de la saison, sans aucune licence validée sur une saison antérieure.
        // On se base sur la licence, pas sur createdAt : les inscriptions d'une
        // saison ouvrent avant le 1er septembre (hors fenêtre calendaire) et les
        // renouvellements gardent le createdAt de la première adhésion.
        $newThisSeason = (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere($pop)
            ->andWhere('NOT EXISTS (SELECT lo.id FROM '.License::class.' lo WHERE lo.member = m AND lo.season < :season AND lo.status IN (:validated))')
            ->setParameter('season', $season)
            ->setParameter('validated', $validated)
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
                'newThisSeason' => $newThisSeason,
                'byMonth'       => array_map(fn($r) => [
                    'month' => $r['month'],
                    'total' => (int) $r['total'],
                ], $byMonthRaw),
            ],
        ];
    }

    /**
     * Membre distinct partageant le même email (hors membre exclu), le plus
     * ancien d'abord. Sert à détecter un doublon lors d'une réinscription.
     */
    public function findOneByEmailExcluding(string $email, int $excludeId): ?Member
    {
        return $this->createQueryBuilder('m')
            ->andWhere('LOWER(m.email) = LOWER(:email)')
            ->andWhere('m.id != :excludeId')
            ->setParameter('email', $email)
            ->setParameter('excludeId', $excludeId)
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Member[]
     */
    public function findByTeam(Team $team, ?string $season = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.teams', 't')
            ->addSelect('t')
            ->andWhere(':team MEMBER OF m.teams')
            ->setParameter('team', $team);

        // Scopée à la saison (même population que la liste des licenciés) : le
        // membre doit avoir une licence validée pour cette saison.
        if ($season !== null) {
            $qb->andWhere('EXISTS (SELECT ls.id FROM '.License::class.' ls WHERE ls.member = m AND ls.season = :season AND ls.status IN (:validatedStatuses))')
                ->setParameter('season', $season)
                ->setParameter('validatedStatuses', LicenseStatus::activeMembership());
        }

        return $qb->orderBy('m.lastName', 'ASC')
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
