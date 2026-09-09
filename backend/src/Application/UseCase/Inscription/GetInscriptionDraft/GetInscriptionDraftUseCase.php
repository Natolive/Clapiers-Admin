<?php

namespace App\Application\UseCase\Inscription\GetInscriptionDraft;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\InscriptionDraft;
use App\Repository\InscriptionDraftRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reprise d'une inscription : champs saisis + pièces déjà reçues (nom, type,
 * taille). Jamais les fichiers, ni leur chemin de stockage.
 *
 * @extends AbstractUseCase<GetInscriptionDraftCommand>
 */
class GetInscriptionDraftUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly InscriptionDraftRepository $drafts,
    ) {
    }

    public function run(?CommandInterface $command = null): InscriptionDraft
    {
        if (!$command instanceof GetInscriptionDraftCommand) {
            throw new UseCaseException('Invalid command');
        }

        $draft = $this->drafts->findOneByToken($command->token);
        if (!$draft) {
            throw new UseCaseException('Brouillon introuvable', Response::HTTP_NOT_FOUND);
        }

        return $draft;
    }
}
