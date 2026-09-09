<?php

namespace App\Application\UseCase\Inscription\UploadInscriptionDraftDocument;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\MemberMediaStorage;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\InscriptionDraft;
use App\Repository\InscriptionDraftRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dépôt d'une pièce sur le brouillon, dès que la personne l'a choisie : le
 * fichier part sous `drafts/<token>/` et n'ira dans la médiathèque du membre
 * qu'à la validation. Un nouvel envoi sur le même slot remplace le précédent.
 *
 * C'est le seul appel lourd du formulaire, et il est désormais isolé : une
 * pièce refusée (413 de l'ingress, mimetype, taille) ne peut plus faire naître
 * une demande sans ses pièces, et se rejoue sans rien perdre.
 *
 * @extends AbstractUseCase<UploadInscriptionDraftDocumentCommand>
 */
class UploadInscriptionDraftDocumentUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly InscriptionDraftRepository $drafts,
        private readonly MemberMediaStorage $storage,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function run(?CommandInterface $command = null): InscriptionDraft
    {
        if (!$command instanceof UploadInscriptionDraftDocumentCommand) {
            throw new UseCaseException('Invalid command');
        }

        // La photo de profil doit être une image (pas un PDF) — même règle que
        // le dépôt par magic link.
        if ($command->systemKey === 'identity_photo' && !str_starts_with((string) $command->file->getMimeType(), 'image/')) {
            throw new UseCaseException('La photo de profil doit être une image.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $draft = $this->drafts->findOneByToken($command->token);
        if (!$draft) {
            throw new UseCaseException('Brouillon introuvable', Response::HTTP_NOT_FOUND);
        }

        $meta = $this->storage->storeIn($draft->storagePrefix(), $command->file);
        $previous = $draft->putDocument($command->systemKey, $meta);
        $this->entityManager->flush();

        // L'ancien fichier ne part qu'une fois le nouveau nom commité : le
        // supprimer avant laisserait le brouillon pointer sur un objet effacé
        // si le flush échouait.
        $this->storage->delete($previous);

        return $draft;
    }
}
