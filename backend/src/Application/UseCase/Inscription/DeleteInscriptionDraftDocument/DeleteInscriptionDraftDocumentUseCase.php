<?php

namespace App\Application\UseCase\Inscription\DeleteInscriptionDraftDocument;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\MemberMediaStorage;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\InscriptionDraft;
use App\Repository\InscriptionDraftRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Retire une pièce du brouillon (bouton « Retirer » du champ de dépôt).
 * Retirer une pièce absente n'est pas une erreur.
 *
 * @extends AbstractUseCase<DeleteInscriptionDraftDocumentCommand>
 */
class DeleteInscriptionDraftDocumentUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly InscriptionDraftRepository $drafts,
        private readonly MemberMediaStorage $storage,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function run(?CommandInterface $command = null): InscriptionDraft
    {
        if (!$command instanceof DeleteInscriptionDraftDocumentCommand) {
            throw new UseCaseException('Invalid command');
        }

        $draft = $this->drafts->findOneByToken($command->token);
        if (!$draft) {
            throw new UseCaseException('Brouillon introuvable', Response::HTTP_NOT_FOUND);
        }

        $previous = $draft->removeDocument($command->systemKey);
        $this->entityManager->flush();

        // Après le flush, toujours : la base ne pointe plus dessus.
        $this->storage->delete($previous);

        return $draft;
    }
}
