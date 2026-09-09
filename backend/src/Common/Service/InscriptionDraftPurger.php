<?php

namespace App\Common\Service;

use App\Repository\InscriptionDraftRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Ménage des brouillons d'inscription abandonnés : au bout de 30 jours sans
 * activité, la ligne et ses fichiers Bunny disparaissent.
 *
 * Appliqué au fil de l'eau, une fois par process, à l'ouverture d'un brouillon
 * — même parti pris que {@see \App\Logger\DoctrineHandler::pruneOldLogs()} :
 * pas de cron à planifier, et une seule passe par requête qui en crée un. Un
 * échec de ménage ne doit jamais faire échouer l'inscription en cours.
 */
class InscriptionDraftPurger
{
    private const RETENTION_DAYS = 30;

    private bool $purged = false;

    public function __construct(
        private readonly InscriptionDraftRepository $drafts,
        private readonly MemberMediaStorage $storage,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** Nombre de brouillons purgés (0 si déjà fait dans ce process, ou en cas d'échec). */
    public function purgeOnce(): int
    {
        if ($this->purged) {
            return 0;
        }
        // Marqué avant exécution : un échec ne doit pas être retenté en boucle.
        $this->purged = true;

        try {
            $expired = $this->drafts->findInactiveSince(self::RETENTION_DAYS);

            $storedNames = [];
            foreach ($expired as $draft) {
                $storedNames = [...$storedNames, ...$draft->storedNames()];
                $this->entityManager->remove($draft);
            }
            $this->entityManager->flush();

            // Après le flush : plus aucune ligne ne pointe sur ces objets.
            foreach ($storedNames as $storedName) {
                $this->storage->delete($storedName);
            }

            return \count($expired);
        } catch (\Throwable $e) {
            $this->logger->warning("Purge des brouillons d'inscription impossible", ['exception' => $e]);

            return 0;
        }
    }
}
