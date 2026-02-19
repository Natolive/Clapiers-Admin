<?php

namespace App\Controller;

use App\Application\UseCase\User\CreateUpdateUser\CreateUpdateUserCommand;
use App\Application\UseCase\User\CreateUpdateUser\CreateUpdateUserUseCase;
use App\Application\UseCase\User\GetAllUsersUseCase;
use App\Application\UseCase\User\GetPaginatedUsers\GetPaginatedUsersCommand;
use App\Application\UseCase\User\GetPaginatedUsers\GetPaginatedUsersUseCase;
use App\Application\UseCase\User\LinkMember\LinkMemberCommand;
use App\Application\UseCase\User\LinkMember\LinkMemberUseCase;
use App\Application\UseCase\User\UnlinkMember\UnlinkMemberCommand;
use App\Application\UseCase\User\UnlinkMember\UnlinkMemberUseCase;
use App\Entity\AppUser;
use App\Entity\Enum\AppUserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/user', name: 'api_user_')]
class UserController extends AbstractController
{
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): Response
    {
        /** @var AppUser $user */
        $user = $this->getUser();
        return $this->json($user->toArray());
    }

    #[Route('/paginated', name: 'get_paginated', methods: ['GET'])]
    #[IsGranted(AppUserRole::ROLE_SUPER_ADMIN)]
    public function getPaginated(
        #[MapQueryString] GetPaginatedUsersCommand $command,
        GetPaginatedUsersUseCase $useCase
    ): Response {
        return $useCase->execute($command);
    }

    #[Route('', name: 'get_all', methods: ['GET'])]
    #[IsGranted(AppUserRole::ROLE_SUPER_ADMIN)]
    public function getAll(GetAllUsersUseCase $useCase): Response
    {
        return $useCase->execute();
    }

    #[Route('', name: 'create_update', methods: ['POST', 'PUT'])]
    #[IsGranted(AppUserRole::ROLE_SUPER_ADMIN)]
    public function createUpdate(
        #[MapRequestPayload] CreateUpdateUserCommand $command,
        CreateUpdateUserUseCase $useCase
    ): Response {
        return $useCase->execute($command);
    }

    #[Route('/{id}/link-member', name: 'link_member', methods: ['PATCH'])]
    #[IsGranted(AppUserRole::ROLE_SUPER_ADMIN)]
    public function linkMember(
        int $id,
        Request $request,
        LinkMemberUseCase $useCase
    ): Response {
        $data = json_decode($request->getContent(), true);
        $command = new LinkMemberCommand($id, (int) ($data['memberId'] ?? 0));

        return $useCase->execute($command);
    }

    #[Route('/{id}/unlink-member', name: 'unlink_member', methods: ['PATCH'])]
    #[IsGranted(AppUserRole::ROLE_SUPER_ADMIN)]
    public function unlinkMember(
        int $id,
        UnlinkMemberUseCase $useCase
    ): Response {
        $command = new UnlinkMemberCommand($id);

        return $useCase->execute($command);
    }
}
