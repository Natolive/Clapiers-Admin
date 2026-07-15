<?php

namespace App\Controller;

use App\Application\UseCase\Member\Media\CreateDocument\CreateDocumentCommand;
use App\Application\UseCase\Member\Media\CreateDocument\CreateDocumentUseCase;
use App\Application\UseCase\Member\Media\CreateFolder\CreateFolderCommand;
use App\Application\UseCase\Member\Media\CreateFolder\CreateFolderPayload;
use App\Application\UseCase\Member\Media\CreateFolder\CreateFolderUseCase;
use App\Application\UseCase\Member\Media\DeleteDocumentFile\DeleteDocumentFileCommand;
use App\Application\UseCase\Member\Media\DeleteDocumentFile\DeleteDocumentFileUseCase;
use App\Application\UseCase\Member\Media\DeleteNode\DeleteNodeCommand;
use App\Application\UseCase\Member\Media\DeleteNode\DeleteNodeUseCase;
use App\Application\UseCase\Member\Media\GetMemberMedia\GetMemberMediaCommand;
use App\Application\UseCase\Member\Media\GetMemberMedia\GetMemberMediaUseCase;
use App\Application\UseCase\Member\Media\RenameNode\RenameNodeCommand;
use App\Application\UseCase\Member\Media\RenameNode\RenameNodePayload;
use App\Application\UseCase\Member\Media\RenameNode\RenameNodeUseCase;
use App\Application\UseCase\Member\Media\UploadDocumentFile\UploadDocumentFileCommand;
use App\Application\UseCase\Member\Media\UploadDocumentFile\UploadDocumentFileUseCase;
use App\Entity\Enum\AppUserRole;
use App\Repository\MemberDocumentRepository;
use App\Repository\MemberRepository;
use App\Common\Service\MemberMediaStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Médiathèque d'un membre (dossiers/documents par saison). SUPER_ADMIN uniquement.
 */
#[Route('/api/member/{id}/media', name: 'api_member_media_')]
#[IsGranted(AppUserRole::ROLE_SUPER_ADMIN)]
class MemberMediaController extends AbstractController
{
    #[Route('', name: 'get', methods: ['GET'])]
    public function get(int $id, GetMemberMediaUseCase $useCase): Response
    {
        return $useCase->execute(new GetMemberMediaCommand($id));
    }

    #[Route('/folder', name: 'create_folder', methods: ['POST'])]
    public function createFolder(
        int $id,
        #[MapRequestPayload] CreateFolderPayload $payload,
        CreateFolderUseCase $useCase,
    ): Response {
        return $useCase->execute(new CreateFolderCommand($id, $payload->name, $payload->parentId));
    }

    #[Route('/document', name: 'create_document', methods: ['POST'])]
    public function createDocument(
        int $id,
        Request $request,
        #[MapUploadedFile([new Assert\File(maxSize: '10M', mimeTypes: ['application/pdf', 'image/png', 'image/jpeg'])])] UploadedFile $file,
        CreateDocumentUseCase $useCase,
    ): Response {
        $name = (string) $request->request->get('name', '');
        $parentId = $request->request->get('parentId');

        return $useCase->execute(new CreateDocumentCommand(
            $id,
            $name,
            $file,
            $parentId !== null && $parentId !== '' ? (string) $parentId : null,
        ));
    }

    #[Route('/node/{uuid}/file', name: 'upload_file', methods: ['POST'])]
    public function uploadFile(
        int $id,
        string $uuid,
        #[MapUploadedFile([new Assert\File(maxSize: '10M', mimeTypes: ['application/pdf', 'image/png', 'image/jpeg'])])] UploadedFile $file,
        UploadDocumentFileUseCase $useCase,
    ): Response {
        return $useCase->execute(new UploadDocumentFileCommand($id, $uuid, $file));
    }

    #[Route('/node/{uuid}/file', name: 'delete_file', methods: ['DELETE'])]
    public function deleteFile(
        int $id,
        string $uuid,
        DeleteDocumentFileUseCase $useCase,
    ): Response {
        return $useCase->execute(new DeleteDocumentFileCommand($id, $uuid));
    }

    #[Route('/node/{uuid}', name: 'rename', methods: ['PATCH'])]
    public function rename(
        int $id,
        string $uuid,
        #[MapRequestPayload] RenameNodePayload $payload,
        RenameNodeUseCase $useCase,
    ): Response {
        return $useCase->execute(new RenameNodeCommand($id, $uuid, $payload->name));
    }

    #[Route('/node/{uuid}', name: 'delete', methods: ['DELETE'])]
    public function delete(
        int $id,
        string $uuid,
        DeleteNodeUseCase $useCase,
    ): Response {
        return $useCase->execute(new DeleteNodeCommand($id, $uuid));
    }

    #[Route('/node/{uuid}/download', name: 'download', methods: ['GET'])]
    public function download(
        int $id,
        string $uuid,
        MemberRepository $memberRepository,
        MemberDocumentRepository $documentRepository,
        MemberMediaStorage $storage,
    ): Response {
        $member = $memberRepository->find($id);
        if (!$member) {
            return $this->json(['error' => 'Document introuvable'], Response::HTTP_NOT_FOUND);
        }

        $node = $documentRepository->findOneOwnedBy($member, $uuid);
        if (!$node || !$node->hasFile()) {
            return $this->json(['error' => 'Document introuvable'], Response::HTTP_NOT_FOUND);
        }

        $path = $storage->path((string) $node->getStoredName());
        if (!is_file($path)) {
            return $this->json(['error' => 'Document introuvable'], Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $node->getOriginalName() ?? $node->getName(),
        );

        return $response;
    }
}
