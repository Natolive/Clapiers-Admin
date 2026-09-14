<?php

namespace App\Common\Service;

use App\Common\Exception\UseCaseException;
use App\Entity\Member;
use App\Entity\MemberDocument;
use App\Repository\MemberDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Où atterrit une pièce d'inscription dans la médiathèque du membre — il n'y a
 * pas de stockage « documents de demande » séparé :
 *  - `identity_photo` / `id_card` → dossier racine « Identité » ;
 *  - `medical_certificate` / `attestation` → dossier de la saison de la licence.
 *
 * Partagé par le dépôt via magic link ({@see \App\Application\UseCase\License\UploadLicenseRequestDocument\UploadLicenseRequestDocumentUseCase})
 * et par le rattachement des pièces d'un brouillon à la validation
 * ({@see \App\Application\UseCase\License\SubmitLicenseRequest\SubmitLicenseRequestUseCase}) :
 * les deux ont besoin du même mapping et du même piège de flush.
 */
class MemberMediaSlots
{
    /** Slots déposables publiquement à l'inscription, par portée. */
    public const ROOT_KEYS = ['identity_photo', 'id_card'];
    public const SEASON_KEYS = ['medical_certificate', 'attestation'];

    public function __construct(
        private readonly MemberDocumentRepository $documentRepository,
        private readonly MemberMediaSeeder $seeder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Slot où déposer la pièce, créé au besoin. Lève une `UseCaseException`
     * plutôt que de renvoyer null : tous les appelants en font une réponse
     * d'erreur, aucun n'a de repli.
     */
    public function resolve(Member $member, string $season, string $systemKey): MemberDocument
    {
        $isRoot = in_array($systemKey, self::ROOT_KEYS, true);
        if (!$isRoot && !in_array($systemKey, self::SEASON_KEYS, true)) {
            throw new UseCaseException('Type de document invalide', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Garantit l'existence des slots par défaut, puis flush : le seeder ne
        // flushe pas, or la requête ci-dessous tape la base — sans flush elle ne
        // verrait pas les slots fraîchement créés.
        if ($isRoot) {
            $this->seeder->ensureRootFolders($member);
        } else {
            $this->seeder->ensureSeason($member, $season);
        }
        $this->entityManager->flush();

        $slot = $isRoot
            ? $this->documentRepository->findRootDocumentSlot($member, $systemKey)
            : $this->documentRepository->findDefaultSlot($member, $season, $systemKey);

        if (!$slot) {
            throw new UseCaseException('Slot médiathèque introuvable', Response::HTTP_NOT_FOUND);
        }

        return $slot;
    }
}
