<?php

namespace App\Common\Service;

use App\Entity\Member;
use App\Repository\MemberDocumentRepository;

/**
 * URL CDN signées des photos de profil, pour toute une liste de licenciés en
 * une requête. Sans ça chaque avatar coûtait un aller-retour authentifié vers
 * le backend ; avec, le navigateur va chercher l'image au bord et la garde en
 * cache ({@see MemberMediaStorage::DISPLAY_TTL}).
 *
 * Tableau volontairement creux : pas de photo, ou pas de pull zone configurée,
 * et l'entrée est absente — l'appelant renvoie `null` et le front retombe sur
 * la route qui streame.
 */
class MemberPhotoUrls
{
    private const SLOT = 'identity_photo';

    public function __construct(
        private readonly MemberDocumentRepository $documentRepository,
        private readonly MemberMediaStorage $storage,
    ) {
    }

    /**
     * @param list<Member> $members
     *
     * @return array<int, string> id du membre => URL signée
     */
    public function forMembers(array $members, string $season): array
    {
        $ids = array_map(static fn (Member $m) => (int) $m->getId(), $members);
        $slots = $this->documentRepository->findDefaultSlotsForMembers($ids, $season, [self::SLOT]);

        $urls = [];
        foreach ($slots as $memberId => $byKey) {
            $url = $this->storage->signedUrl(
                $byKey[self::SLOT]?->getStoredName(),
                MemberMediaStorage::DISPLAY_TTL,
            );

            if ($url !== null) {
                $urls[$memberId] = $url;
            }
        }

        return $urls;
    }
}
