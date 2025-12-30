<?php

namespace App\Controller;

use App\Application\UseCase\Team\CreateUpdateTeam\CreateUpdateTeamCommand;
use App\Application\UseCase\Team\CreateUpdateTeam\CreateUpdateTeamUseCase;
use App\Application\UseCase\Team\GetAllTeamsUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/team', name: 'api_team_')]
class TeamController extends AbstractController
{
    #[Route('', name: 'get_all', methods: ['GET'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function getAll(GetAllTeamsUseCase $useCase): Response
    {
        return $useCase->execute();
    }

    #[Route('', name: 'create_update', methods: ['POST', 'PUT'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function create(
        #[MapRequestPayload] CreateUpdateTeamCommand $command,
        CreateUpdateTeamUseCase $useCase
    ): Response {
        return $useCase->execute($command);
    }
}
