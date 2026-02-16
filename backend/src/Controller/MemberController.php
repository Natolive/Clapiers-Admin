<?php

namespace App\Controller;

use App\Application\UseCase\Member\CreateUpdateMember\CreateUpdateMemberCommand;
use App\Application\UseCase\Member\CreateUpdateMember\CreateUpdateMemberUseCase;
use App\Application\UseCase\Member\GetAllMembersUseCase;
use App\Application\UseCase\Member\GetMembersByTeam\GetMembersByTeamCommand;
use App\Application\UseCase\Member\GetMembersByTeam\GetMembersByTeamUseCase;
use App\Application\UseCase\Member\DeleteLicense\DeleteLicenseCommand;
use App\Application\UseCase\Member\DeleteLicense\DeleteLicenseUseCase;
use App\Application\UseCase\Member\UploadLicense\UploadLicenseCommand;
use App\Application\UseCase\Member\UploadLicense\UploadLicenseUseCase;
use App\Entity\Enum\AppUserRole;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/member', name: 'api_member_')]
#[IsGranted(AppUserRole::ROLE_SUPER_ADMIN)]
class MemberController extends AbstractController
{
    #[Route('', name: 'get_all', methods: ['GET'])]
    public function getAll(GetAllMembersUseCase $useCase): Response
    {
        return $useCase->execute();
    }

    #[Route('', name: 'create_update', methods: ['POST', 'PUT'])]
    public function create(
        #[MapRequestPayload] CreateUpdateMemberCommand $command,
        CreateUpdateMemberUseCase $useCase
    ): Response {
        return $useCase->execute($command);
    }

    #[Route('/team/{teamId}', name: 'get_by_team', methods: ['GET'])]
    public function getByTeam(int $teamId, GetMembersByTeamUseCase $useCase): Response
    {
        $command = new GetMembersByTeamCommand($teamId);
        return $useCase->execute($command);
    }

    #[Route('/{id}/toggle-license', name: 'toggle_license', methods: ['PATCH'])]
    public function toggleLicense(
        int $id,
        MemberRepository $memberRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $member = $memberRepository->find($id);

        if (!$member) {
            return $this->json(['error' => 'Member not found'], 404);
        }

        $member->setLicensePaid(!$member->isLicensePaid());
        $entityManager->flush();

        return $this->json($member->toArray());
    }

    #[Route('/{id}/upload-license', name: 'upload_license', methods: ['POST'])]
    public function uploadLicense(
        int $id,
        #[MapUploadedFile] UploadedFile $file,
        UploadLicenseUseCase $useCase
    ): Response {
        $command = new UploadLicenseCommand($id, $file);

        return $useCase->execute($command);
    }

    #[Route('/{id}/delete-license', name: 'delete_license', methods: ['DELETE'])]
    public function deleteLicense(
        int $id,
        DeleteLicenseUseCase $useCase
    ): Response {
        $command = new DeleteLicenseCommand($id);

        return $useCase->execute($command);
    }

    #[Route('/{id}/download-license', name: 'download_license', methods: ['GET'])]
    public function downloadLicense(
        int $id,
        MemberRepository $memberRepository,
        #[Autowire('%upload_directory%')] string $uploadDirectory
    ): Response {
        $member = $memberRepository->find($id);

        if (!$member || !$member->getLicenseFileName()) {
            return $this->json(['error' => 'License file not found'], 404);
        }

        $filePath = $uploadDirectory . '/licenses/' . $member->getLicenseFileName();

        if (!file_exists($filePath)) {
            return $this->json(['error' => 'License file not found'], 404);
        }

        return new BinaryFileResponse($filePath);
    }
}
