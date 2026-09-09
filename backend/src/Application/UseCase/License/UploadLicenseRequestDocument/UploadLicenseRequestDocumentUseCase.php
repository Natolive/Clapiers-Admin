<?php

namespace App\Application\UseCase\License\UploadLicenseRequestDocument;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\MemberMediaSlots;
use App\Common\Service\MemberMediaStorage;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\License;
use App\Repository\LicenseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dépôt public d'une pièce d'inscription via le magic link. Chaque fichier
 * atterrit directement dans le slot médiathèque du membre (source unique) :
 *  - identity_photo / id_card → dossier racine « Identité » ;
 *  - medical_certificate       → dossier de la saison de la licence.
 *
 * @extends AbstractUseCase<UploadLicenseRequestDocumentCommand>
 */
class UploadLicenseRequestDocumentUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly LicenseRepository $licenseRepository,
        private readonly MemberMediaSlots $slots,
        private readonly MemberMediaStorage $storage,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function run(?CommandInterface $command = null): License
    {
        if (!$command instanceof UploadLicenseRequestDocumentCommand) {
            throw new UseCaseException('Invalid command');
        }

        // La photo de profil doit être une image (pas un PDF).
        if ($command->systemKey === 'identity_photo' && !str_starts_with((string) $command->file->getMimeType(), 'image/')) {
            throw new UseCaseException('La photo de profil doit être une image.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $license = $this->licenseRepository->findOneByAccessToken($command->token);
        if (!$license) {
            throw new UseCaseException('Licence introuvable', Response::HTTP_NOT_FOUND);
        }

        if ($license->isTokenExpired()) {
            throw new UseCaseException(
                'Ce lien a expiré. Contactez le club pour en recevoir un nouveau.',
                Response::HTTP_GONE,
            );
        }

        $member = $license->getMember();
        $slot = $this->slots->resolve($member, $license->getSeason(), $command->systemKey);

        $previous = $slot->getStoredName();
        $meta = $this->storage->store($command->file, (int) $member->getId());
        $slot->setFile($meta['storedName'], $meta['originalName'], $meta['mimeType'], $meta['size']);

        // Marqueur sur la licence pour le badge « certificat déposé » côté admin.
        if ($command->systemKey === 'medical_certificate') {
            $license->setMedicalCertificateFileName($meta['storedName']);
        }

        $this->entityManager->flush();

        // L'ancien fichier ne part qu'une fois le nouveau nom commité : le
        // supprimer avant laisserait la base pointer sur un objet déjà effacé
        // si le store (502 Bunny) ou le flush échouait.
        $this->storage->deleteQuietly($previous);

        return $license;
    }
}
