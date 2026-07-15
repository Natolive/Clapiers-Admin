<?php

namespace App\Application\UseCase\Member\Media\DeleteDocumentFile;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\MemberMediaStorage;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\MemberDocument;
use App\Repository\MemberDocumentRepository;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vide le fichier d'un document en conservant le nœud (utile pour les slots
 * par défaut, non supprimables mais dont on peut retirer la pièce).
 *
 * @extends AbstractUseCase<DeleteDocumentFileCommand>
 */
class DeleteDocumentFileUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly MemberDocumentRepository $documentRepository,
        private readonly MemberMediaStorage $storage,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function run(?CommandInterface $command = null): MemberDocument
    {
        if (!$command instanceof DeleteDocumentFileCommand) {
            throw new UseCaseException('Invalid command');
        }

        $member = $this->memberRepository->find($command->memberId);
        if (!$member) {
            throw new UseCaseException('Member not found', Response::HTTP_NOT_FOUND);
        }

        $node = $this->documentRepository->findOneOwnedBy($member, $command->nodeId);
        if (!$node) {
            throw new UseCaseException('Document introuvable', Response::HTTP_NOT_FOUND);
        }
        if ($node->isFolder()) {
            throw new UseCaseException('Un dossier ne porte pas de fichier');
        }

        $this->storage->delete($node->getStoredName());
        $node->clearFile();
        $this->entityManager->flush();

        return $node;
    }
}
