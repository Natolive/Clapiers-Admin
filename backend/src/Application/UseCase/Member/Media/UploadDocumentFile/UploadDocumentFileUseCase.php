<?php

namespace App\Application\UseCase\Member\Media\UploadDocumentFile;

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
 * (Ré)attache un fichier à un document existant — y compris un slot par défaut.
 *
 * @extends AbstractUseCase<UploadDocumentFileCommand>
 */
class UploadDocumentFileUseCase extends AbstractUseCase
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
        if (!$command instanceof UploadDocumentFileCommand) {
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
            throw new UseCaseException('Un dossier ne peut pas porter de fichier');
        }

        // Remplace l'éventuel fichier précédent.
        $previous = $node->getStoredName();

        $meta = $this->storage->store($command->file, (int) $member->getId());
        $node->setFile($meta['storedName'], $meta['originalName'], $meta['mimeType'], $meta['size']);

        $this->entityManager->flush();

        // L'ancien fichier ne part qu'une fois le nouveau nom commité : le
        // supprimer avant laisserait la base pointer sur un objet déjà effacé
        // si le store (502 Bunny) ou le flush échouait.
        $this->storage->deleteQuietly($previous);

        return $node;
    }
}
