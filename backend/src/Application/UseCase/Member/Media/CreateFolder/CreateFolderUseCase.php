<?php

namespace App\Application\UseCase\Member\Media\CreateFolder;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Enum\MemberDocumentType;
use App\Entity\MemberDocument;
use App\Repository\MemberDocumentRepository;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractUseCase<CreateFolderCommand>
 */
class CreateFolderUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly MemberDocumentRepository $documentRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function run(?CommandInterface $command = null): MemberDocument
    {
        if (!$command instanceof CreateFolderCommand) {
            throw new UseCaseException('Invalid command');
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

        $folder = (new MemberDocument())
            ->setMember($member)
            ->setParent($parent)
            ->setType(MemberDocumentType::FOLDER)
            ->setName($command->name);

        $this->entityManager->persist($folder);
        $this->entityManager->flush();

        return $folder;
    }
}
