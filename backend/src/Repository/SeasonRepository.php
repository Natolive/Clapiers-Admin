<?php

namespace App\Repository;

use App\Entity\Season;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Season>
 */
class SeasonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Season::class);
    }

    /**
     * @return string[] noms de saison enregistrés, la plus récente d'abord
     */
    public function findAllNames(): array
    {
        return array_column(
            $this->createQueryBuilder('s')
                ->select('s.name')
                ->orderBy('s.name', 'DESC')
                ->getQuery()
                ->getScalarResult(),
            'name'
        );
    }

    /** Enregistre la saison si elle n'existe pas encore (idempotent). */
    public function ensure(string $name): void
    {
        if ($this->findOneBy(['name' => $name]) !== null) {
            return;
        }

        $this->getEntityManager()->persist((new Season())->setName($name));
        $this->getEntityManager()->flush();
    }
}
