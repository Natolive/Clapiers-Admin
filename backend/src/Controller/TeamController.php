<?php

namespace App\Controller;

use App\Application\UseCase\Team\CountTeamsBySeason\CountTeamsBySeasonCommand;
use App\Application\UseCase\Team\CountTeamsBySeason\CountTeamsBySeasonUseCase;
use App\Application\UseCase\Team\CreateUpdateTeam\CreateUpdateTeamCommand;
use App\Application\UseCase\Team\CreateUpdateTeam\CreateUpdateTeamUseCase;
use App\Application\UseCase\Team\GetAllTeamsUseCase;
use App\Entity\Enum\AppUserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/team', name: 'api_team_')]
#[IsGranted(AppUserRole::ROLE_SUPER_ADMIN)]
class TeamController extends AbstractController
{
    #[Route('', name: 'get_all', methods: ['GET'])]
    public function getAll(GetAllTeamsUseCase $useCase): Response
    {
        return $useCase->execute();
    }

    #[Route('', name: 'create_update', methods: ['POST', 'PUT'])]
    public function create(
        #[MapRequestPayload] CreateUpdateTeamCommand $command,
        CreateUpdateTeamUseCase $useCase
    ): Response {
        return $useCase->execute($command);
    }

    #[Route('/count-by-season', name: 'count_by_season', methods: ['POST'])]
    public function countBySeason(
        #[MapRequestPayload] CountTeamsBySeasonCommand $command,
        CountTeamsBySeasonUseCase $useCase
    ): Response {
        return $useCase->execute($command);
    }
}
