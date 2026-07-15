<?php

namespace App\Common\Service;

use App\Entity\Enum\MemberDocumentType;
use App\Entity\Member;
use App\Entity\MemberDocument;
use App\Entity\MemberMediaDefaults;
use App\Repository\MemberDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Garantit le "mapping par défaut" de la médiathèque d'un membre :
 *  - les dossiers racine indépendants de la saison (Identité → photo, pièce…) ;
 *  - pour une saison donnée, son dossier + ses slots documents par défaut.
 * Idempotent — n'effectue jamais le flush (à la charge de l'appelant).
 */
class MemberMediaSeeder
{
    public function __construct(
        private readonly MemberDocumentRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** Crée si absents les dossiers racine par défaut (indépendants de la saison). */
    public function ensureRootFolders(Member $member): void
    {
        foreach (MemberMediaDefaults::ROOT_FOLDERS as $systemKey => $config) {
            if ($this->repository->findRootFolder($member, $systemKey) !== null) {
                continue;
            }

            $folder = $this->makeNode($member, MemberDocumentType::FOLDER, $config['label'], $systemKey);
            $this->entityManager->persist($folder);

            foreach ($config['documents'] as $docKey => $label) {
                $doc = $this->makeNode($member, MemberDocumentType::DOCUMENT, $label, $docKey)->setParent($folder);
                $this->entityManager->persist($doc);
                $folder->getChildren()->add($doc);
            }
        }
    }

    /**
     * Crée si absent le dossier de la saison + ses slots par défaut, et renvoie
     * le dossier de saison (existant ou fraîchement créé).
     */
    public function ensureSeason(Member $member, string $season): MemberDocument
    {
        $folder = $this->repository->findSeasonFolder($member, $season);
        if ($folder !== null) {
            return $folder;
        }

        $folder = $this->makeNode($member, MemberDocumentType::FOLDER, $season, MemberMediaDefaults::SEASON_KEY)
            ->setSeason($season);
        $this->entityManager->persist($folder);

        foreach (MemberMediaDefaults::SEASON_DOCUMENTS as $systemKey => $label) {
            $slot = $this->makeNode($member, MemberDocumentType::DOCUMENT, $label, $systemKey)->setParent($folder);
            $this->entityManager->persist($slot);
            // Garde l'inverse cohérent en mémoire : le dossier est sérialisé dans
            // la même requête (GET), avant tout rechargement depuis la base.
            $folder->getChildren()->add($slot);
        }

        return $folder;
    }

    private function makeNode(Member $member, MemberDocumentType $type, string $name, string $systemKey): MemberDocument
    {
        return (new MemberDocument())
            ->setMember($member)
            ->setType($type)
            ->setName($name)
            ->setSystemKey($systemKey)
            ->setProtected(true);
    }
}
