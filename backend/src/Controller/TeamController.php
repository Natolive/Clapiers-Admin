<?php

namespace App\Controller;

use App\Application\UseCase\Team\CreateUpdateTeam\CreateUpdateTeamCommand;
use App\Application\UseCase\Team\CreateUpdateTeam\CreateUpdateTeamUseCase;
use App\Application\UseCase\Team\GetAllTeamsUseCase;
use App\Application\UseCase\Team\DownloadMyTeamMemberLicense\DownloadMyTeamMemberLicenseCommand;
use App\Application\UseCase\Team\DownloadMyTeamMemberLicense\DownloadMyTeamMemberLicenseUseCase;
use App\Application\UseCase\Team\GetMyTeam\GetMyTeamCommand;
use App\Application\UseCase\Team\GetMyTeam\GetMyTeamUseCase;
use App\Entity\AppUser;
use App\Entity\Enum\AppUserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/team', name: 'api_team_')]
class TeamController extends AbstractController
{
    #[Route('', name: 'get_all', methods: ['GET'])]
    #[IsGranted(AppUserRole::ROLE_SUPER_ADMIN)]
    public function getAll(GetAllTeamsUseCase $useCase): Response
    {
        return $useCase->execute();
    }

    #[Route('', name: 'create_update', methods: ['POST', 'PUT'])]
    #[IsGranted(AppUserRole::ROLE_SUPER_ADMIN)]
    public function create(
        #[MapRequestPayload] CreateUpdateTeamCommand $command,
        CreateUpdateTeamUseCase $useCase
    ): Response {
        return $useCase->execute($command);
    }

    #[Route('/my-team', name: 'get_my_team', methods: ['GET'])]
    #[IsGranted(AppUserRole::ROLE_ADMIN)]
    public function getMyTeam(GetMyTeamUseCase $useCase): Response
    {
        /** @var AppUser $user */
        $user = $this->getUser();
        $command = new GetMyTeamCommand($user);

        return $useCase->execute($command);
    }

    #[Route('/my-team/license/{memberId}', name: 'download_my_team_license', methods: ['GET'])]
    #[IsGranted(AppUserRole::ROLE_ADMIN)]
    public function downloadMyTeamMemberLicense(
        int $memberId,
        DownloadMyTeamMemberLicenseUseCase $useCase
    ): Response {
        /** @var AppUser $user */
        $user = $this->getUser();
        $command = new DownloadMyTeamMemberLicenseCommand($user, $memberId);

        return $useCase->run($command);
    }
}
