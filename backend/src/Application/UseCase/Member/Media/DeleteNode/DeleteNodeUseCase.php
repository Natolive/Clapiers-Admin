<?php

namespace App\Application\UseCase\Member\Media\DeleteNode;

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
 * Supprime un nœud (et son sous-arbre par cascade), en effaçant du disque les
 * fichiers portés par le nœud et tous ses descendants. Les nœuds par défaut
 * (protégés) ne sont pas supprimables.
 *
 * @extends AbstractUseCase<DeleteNodeCommand>
 */
class DeleteNodeUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly MemberDocumentRepository $documentRepository,
        private readonly MemberMediaStorage $storage,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{id: string, deleted: bool}
     */
    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof DeleteNodeCommand) {
            throw new UseCaseException('Invalid command');
        }

        $member = $this->memberRepository->find($command->memberId);
        if (!$member) {
            throw new UseCaseException('Member not found', Response::HTTP_NOT_FOUND);
        }

        $node = $this->documentRepository->findOneOwnedBy($member, $command->nodeId);
        if (!$node) {
            throw new UseCaseException('Élément introuvable', Response::HTTP_NOT_FOUND);
        }
        if ($node->isProtected()) {
            throw new UseCaseException('Cet élément par défaut ne peut pas être supprimé');
        }

        $uuid = $node->getUuid();
        $this->deleteFiles($node);

        $this->entityManager->remove($node);
        $this->entityManager->flush();

        return ['id' => $uuid, 'deleted' => true];
    }

    /** Efface récursivement du disque les fichiers du nœud et de ses enfants. */
    private function deleteFiles(MemberDocument $node): void
    {
        $this->storage->delete($node->getStoredName());

        foreach ($node->getChildren() as $child) {
            $this->deleteFiles($child);
        }
    }
}
