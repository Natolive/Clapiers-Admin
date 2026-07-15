<?php

namespace App\Application\UseCase\Member\Media\CreateDocument;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\MemberMediaStorage;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Enum\MemberDocumentType;
use App\Entity\MemberDocument;
use App\Repository\MemberDocumentRepository;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractUseCase<CreateDocumentCommand>
 */
class CreateDocumentUseCase extends AbstractUseCase
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
        if (!$command instanceof CreateDocumentCommand) {
            throw new UseCaseException('Invalid command');
        }

        $name = trim($command->name);
        if ($name === '') {
            throw new UseCaseException('Le nom du document est requis');
        }

        $member = $this->memberRepository->find($command->memberId);
        if (!$member) {
            throw new UseCaseException('Member not found', Response::HTTP_NOT_FOUND);
        }

        $parent = null;
        if ($command->parentId !== null) {
            $parent = $this->documentRepository->findOneOwnedBy($member, $command->parentId);
            if (!$parent) {
                throw new UseCaseException('Parent not found', Response::HTTP_NOT_FOUND);
            }
            if (!$parent->isFolder()) {
                throw new UseCaseException('Le parent doit être un dossier');
            }
        }

        $meta = $this->storage->store($command->file);

        $document = (new MemberDocument())
            ->setMember($member)
            ->setParent($parent)
            ->setType(MemberDocumentType::DOCUMENT)
            ->setName($name)
            ->setFile($meta['storedName'], $meta['originalName'], $meta['mimeType'], $meta['size']);

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        return $document;
    }
}
