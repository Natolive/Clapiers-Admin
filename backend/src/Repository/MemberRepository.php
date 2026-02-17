<?php

namespace App\Repository;

use App\Entity\Member;
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
            'team.name' => 't.name',
        ];

        $orderColumn = $allowedFields[$sortField] ?? 'm.firstName';
        $orderDir = strtolower($sortOrder) === 'desc' ? 'DESC' : 'ASC';

        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.team', 't');

        if ($search) {
            $searchTerm = '%' . $search . '%';
            $qb->andWhere('LOWER(m.firstName) LIKE LOWER(:search) OR LOWER(m.lastName) LIKE LOWER(:search) OR LOWER(m.email) LIKE LOWER(:search) OR m.phoneNumber LIKE :search')
                ->setParameter('search', $searchTerm);
        }

        if ($teamId) {
            $qb->andWhere('t.id = :teamId')
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

        $data = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['data' => $data, 'total' => (int) $total];
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
}
