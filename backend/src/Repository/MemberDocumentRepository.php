<?php

namespace App\Repository;

use App\Entity\Member;
use App\Entity\MemberDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MemberDocument>
 */
class MemberDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MemberDocument::class);
    }

    /**
     * Racines de la médiathèque d'un membre (dossiers de saison, dossiers
     * libres). L'arbre complet est renvoyé via les enfants de chaque nœud.
     *
     * @return MemberDocument[]
     */
    public function findRootsByMember(Member $member): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.member = :member')
            ->andWhere('d.parent IS NULL')
            ->setParameter('member', $member)
            ->getQuery()
            ->getResult();
    }

    /** Dossier racine par défaut indépendant de la saison (Identité, …). */
    public function findRootFolder(Member $member, string $systemKey): ?MemberDocument
    {
        return $this->createQueryBuilder('d')
            ->where('d.member = :member')
            ->andWhere('d.parent IS NULL')
            ->andWhere('d.systemKey = :key')
            ->setParameter('member', $member)
            ->setParameter('key', $systemKey)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Slot document d'un dossier racine (parent hors saison), ex. photo de profil. */
    public function findRootDocumentSlot(Member $member, string $systemKey): ?MemberDocument
    {
        return $this->createQueryBuilder('d')
            ->join('d.parent', 'p')
            ->where('d.member = :member')
            ->andWhere('d.systemKey = :key')
            ->andWhere('p.season IS NULL')
            ->setParameter('member', $member)
            ->setParameter('key', $systemKey)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findSeasonFolder(Member $member, string $season): ?MemberDocument
    {
        return $this->createQueryBuilder('d')
            ->where('d.member = :member')
            ->andWhere('d.parent IS NULL')
            ->andWhere('d.systemKey = :key')
            ->andWhere('d.season = :season')
            ->setParameter('member', $member)
            ->setParameter('key', \App\Entity\MemberMediaDefaults::SEASON_KEY)
            ->setParameter('season', $season)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Slot document par défaut (license, medical_certificate) d'une saison donnée. */
    public function findDefaultSlot(Member $member, string $season, string $systemKey): ?MemberDocument
    {
        return $this->createQueryBuilder('d')
            ->join('d.parent', 'p')
            ->where('d.member = :member')
            ->andWhere('d.systemKey = :key')
            ->andWhere('p.season = :season')
            ->andWhere('p.systemKey = :seasonKey')
            ->setParameter('member', $member)
            ->setParameter('key', $systemKey)
            ->setParameter('season', $season)
            ->setParameter('seasonKey', \App\Entity\MemberMediaDefaults::SEASON_KEY)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Nœud appartenant bien au membre, adressé par son UUID public (ou null). */
    public function findOneOwnedBy(Member $member, string $uuid): ?MemberDocument
    {
        return $this->createQueryBuilder('d')
            ->where('d.uuid = :uuid')
            ->andWhere('d.member = :member')
            ->setParameter('uuid', $uuid)
            ->setParameter('member', $member)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
