<?php

namespace App\Repository;

use App\Entity\SalleClosure;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SalleClosure>
 */
class SalleClosureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SalleClosure::class);
    }

    /**
     * @return SalleClosure[]
     */
    public function findAllOrderedByDate(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Two inclusive ranges overlap when start1 <= end2 AND start2 <= end1.
     */
    public function hasOverlap(\DateTimeImmutable $start, \DateTimeImmutable $end): bool
    {
        $count = (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.startDate <= :end')
            ->andWhere('c.endDate >= :start')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
