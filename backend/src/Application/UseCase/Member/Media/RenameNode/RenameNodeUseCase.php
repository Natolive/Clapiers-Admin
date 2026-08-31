<?php

namespace App\Application\UseCase\Member\Media\RenameNode;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\MemberDocument;
use App\Repository\MemberDocumentRepository;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractUseCase<RenameNodeCommand>
 */
class RenameNodeUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly MemberDocumentRepository $documentRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function run(?CommandInterface $command = null): MemberDocument
    {
        if (!$command instanceof RenameNodeCommand) {
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
            throw new UseCaseException('Cet élément par défaut ne peut pas être renommé');
        }

        $node->setName($command->name);
        $this->entityManager->flush();

        return $node;
    }
}
