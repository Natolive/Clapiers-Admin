<?php

namespace App\Controller;

use App\Application\UseCase\User\CreateUpdateUser\CreateUpdateUserCommand;
use App\Application\UseCase\User\CreateUpdateUser\CreateUpdateUserUseCase;
use App\Application\UseCase\User\GetAllUsersUseCase;
use App\Entity\AppUser;
use App\Entity\Enum\AppUserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
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
}
