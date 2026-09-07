<?php

namespace App\Controller;

use App\Application\UseCase\Member\CreateUpdateMember\CreateUpdateMemberCommand;
use App\Application\UseCase\Member\CreateUpdateMember\CreateUpdateMemberUseCase;
use App\Application\UseCase\Member\GetAllMembersUseCase;
use App\Application\UseCase\Member\GetMembersByTeam\GetMembersByTeamCommand;
use App\Application\UseCase\Member\GetMembersByTeam\GetMembersByTeamUseCase;
use App\Application\UseCase\Member\GetPaginatedMembers\GetPaginatedMembersCommand;
use App\Application\UseCase\Member\GetPaginatedMembers\GetPaginatedMembersUseCase;
use App\Common\Service\MemberMediaStorage;
use App\Controller\Input\SeasonQuery;
use App\Entity\Enum\AppUserRole;
use App\Repository\MemberDocumentRepository;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
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

    #[Route('/paginated', name: 'get_paginated', methods: ['GET'])]
    public function getPaginated(
        #[MapQueryString] GetPaginatedMembersCommand $command,
        GetPaginatedMembersUseCase $useCase
    ): Response {
        return $useCase->execute($command);
    }

    #[Route('/team/{teamId}', name: 'get_by_team', methods: ['GET'])]
    public function getByTeam(int $teamId, #[MapQueryString] ?SeasonQuery $query, GetMembersByTeamUseCase $useCase): Response
    {
        $command = new GetMembersByTeamCommand($teamId, $query?->season);
        return $useCase->execute($command);
    }

    #[Route('/{id}/profile-picture', name: 'profile_picture', methods: ['GET'])]
    #[IsGranted(AppUserRole::ROLE_ADMIN)]
    public function profilePicture(
        int $id,
        MemberRepository $memberRepository,
        MemberDocumentRepository $documentRepository,
        MemberMediaStorage $mediaStorage
    ): Response {
        $member = $memberRepository->find($id);
        if (!$member) {
            return $this->json(['error' => 'Profile picture not found'], 404);
        }

        // La médiathèque est la source unique : slot « Photo d'identité ».
        $slot = $documentRepository->findRootDocumentSlot($member, 'identity_photo');
        if ($slot && $slot->hasFile()) {
            $response = $mediaStorage->response((string) $slot->getStoredName(), $slot->getMimeType());
            if ($response !== null) {
                return $response;
            }
        }

        return $this->json(['error' => 'Profile picture not found'], 404);
    }
}
