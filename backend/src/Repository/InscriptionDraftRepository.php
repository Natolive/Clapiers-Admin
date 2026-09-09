<?php

namespace App\Repository;

use App\Entity\InscriptionDraft;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InscriptionDraft>
 */
class InscriptionDraftRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InscriptionDraft::class);
    }

    public function findOneByToken(string $token): ?InscriptionDraft
    {
        return $this->findOneBy(['token' => $token]);
    }

    /**
     * Brouillons sans activité depuis `$days` jours. Renvoie les entités (et
     * non un DELETE en masse) parce que leurs fichiers Bunny doivent être
     * supprimés un par un avant les lignes.
     *
     * @return InscriptionDraft[]
     */
    public function findInactiveSince(int $days): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.updatedAt < :threshold')
            ->setParameter('threshold', (new \DateTimeImmutable('now'))->modify(sprintf('-%d days', max(1, $days))))
            ->getQuery()
            ->getResult();
    }
}
