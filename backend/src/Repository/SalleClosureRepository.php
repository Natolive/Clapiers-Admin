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
}
