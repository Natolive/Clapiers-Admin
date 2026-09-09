<?php

namespace App\Application\UseCase\Inscription\DeleteInscriptionDraft;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\MemberMediaStorage;
use App\Common\UseCase\AbstractUseCase;
use App\Repository\InscriptionDraftRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Abandon explicite d'un brouillon (« repartir de zéro ») : la ligne et les
 * fichiers déjà déposés disparaissent.
 *
 * @extends AbstractUseCase<DeleteInscriptionDraftCommand>
 */
class DeleteInscriptionDraftUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly InscriptionDraftRepository $drafts,
        private readonly MemberMediaStorage $storage,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array{deleted: bool} */
    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof DeleteInscriptionDraftCommand) {
            throw new UseCaseException('Invalid command');
        }

        $draft = $this->drafts->findOneByToken($command->token);
        if (!$draft) {
            throw new UseCaseException('Brouillon introuvable', Response::HTTP_NOT_FOUND);
        }

        $storedNames = $draft->storedNames();
        $this->entityManager->remove($draft);
        $this->entityManager->flush();

        // Les objets ne partent qu'une fois la ligne supprimée : jamais de
        // brouillon pointant sur un fichier déjà effacé.
        foreach ($storedNames as $storedName) {
            $this->storage->deleteQuietly($storedName);
        }

        return ['deleted' => true];
    }
}
