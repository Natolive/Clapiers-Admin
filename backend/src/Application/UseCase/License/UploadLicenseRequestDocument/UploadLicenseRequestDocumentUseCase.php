<?php

namespace App\Application\UseCase\License\UploadLicenseRequestDocument;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\MemberMediaSeeder;
use App\Common\Service\MemberMediaStorage;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\License;
use App\Repository\LicenseRepository;
use App\Repository\MemberDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dépôt public d'une pièce d'inscription via le magic link. Chaque fichier
 * atterrit directement dans le slot médiathèque du membre (source unique) :
 *  - profile_picture / id_card → dossier racine « Identité » ;
 *  - medical_certificate       → dossier de la saison de la licence.
 *
 * @extends AbstractUseCase<UploadLicenseRequestDocumentCommand>
 */
class UploadLicenseRequestDocumentUseCase extends AbstractUseCase
{
    /** Slots déposables publiquement à l'inscription. */
    private const ROOT_KEYS = ['profile_picture', 'id_card'];
    private const SEASON_KEYS = ['medical_certificate', 'attestation'];

    public function __construct(
        private readonly LicenseRepository $licenseRepository,
        private readonly MemberDocumentRepository $documentRepository,
        private readonly MemberMediaSeeder $seeder,
        private readonly MemberMediaStorage $storage,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function run(?CommandInterface $command = null): License
    {
        if (!$command instanceof UploadLicenseRequestDocumentCommand) {
            throw new UseCaseException('Invalid command');
        }

        $isRoot = in_array($command->systemKey, self::ROOT_KEYS, true);
        $isSeason = in_array($command->systemKey, self::SEASON_KEYS, true);
        if (!$isRoot && !$isSeason) {
            throw new UseCaseException('Type de document invalide', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // La photo de profil doit être une image (pas un PDF).
        if ($command->systemKey === 'profile_picture' && !str_starts_with((string) $command->file->getMimeType(), 'image/')) {
            throw new UseCaseException('La photo de profil doit être une image.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $license = $this->licenseRepository->findOneByAccessToken($command->token);
        if (!$license) {
            throw new UseCaseException('Licence introuvable', Response::HTTP_NOT_FOUND);
        }

        $member = $license->getMember();

        // Garantit l'existence des slots par défaut, puis flush : le seeder ne
        // flushe pas, or la requête ci-dessous tape la base — sans flush elle ne
        // verrait pas les slots fraîchement créés.
        if ($isRoot) {
            $this->seeder->ensureRootFolders($member);
        } else {
            $this->seeder->ensureSeason($member, $license->getSeason());
        }
        $this->entityManager->flush();

        $slot = $isRoot
            ? $this->documentRepository->findRootDocumentSlot($member, $command->systemKey)
            : $this->documentRepository->findDefaultSlot($member, $license->getSeason(), $command->systemKey);

        if (!$slot) {
            throw new UseCaseException('Slot médiathèque introuvable', Response::HTTP_NOT_FOUND);
        }

        $this->storage->delete($slot->getStoredName());
        $meta = $this->storage->store($command->file);
        $slot->setFile($meta['storedName'], $meta['originalName'], $meta['mimeType'], $meta['size']);

        // Marqueur sur la licence pour le badge « certificat déposé » côté admin.
        if ($command->systemKey === 'medical_certificate') {
            $license->setMedicalCertificateFileName($meta['storedName']);
        }

        $this->entityManager->flush();

        return $license;
    }
}
