<?php

namespace App\Controller;

use App\Application\UseCase\Team\CreateUpdateTeam\CreateUpdateTeamCommand;
use App\Application\UseCase\Team\CreateUpdateTeam\CreateUpdateTeamUseCase;
use App\Application\UseCase\Team\DeleteTeam\DeleteTeamCommand;
use App\Application\UseCase\Team\DeleteTeam\DeleteTeamUseCase;
use App\Application\UseCase\Team\GetAllTeams\GetAllTeamsCommand;
use App\Application\UseCase\Team\GetAllTeams\GetAllTeamsUseCase;
use App\Application\UseCase\Team\UpdateTeamRoster\UpdateTeamRosterCommand;
use App\Application\UseCase\Team\UpdateTeamRoster\UpdateTeamRosterUseCase;
use App\Application\UseCase\Team\DownloadMyTeamMemberLicense\DownloadMyTeamMemberLicenseCommand;
use App\Application\UseCase\Team\DownloadMyTeamMemberLicense\DownloadMyTeamMemberLicenseUseCase;
use App\Application\UseCase\Team\DownloadMyTeamMemberPhoto\DownloadMyTeamMemberPhotoCommand;
use App\Application\UseCase\Team\DownloadMyTeamMemberPhoto\DownloadMyTeamMemberPhotoUseCase;
use App\Application\UseCase\Team\GetMyTeam\GetMyTeamCommand;
use App\Application\UseCase\Team\GetMyTeam\GetMyTeamUseCase;
use App\Common\Exception\UseCaseException;
use App\Controller\Input\SeasonQuery;
use App\Controller\Input\UpdateTeamRosterInput;
use App\Entity\AppUser;
use App\Entity\Enum\AppUserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/team', name: 'api_team_')]
class TeamController extends AbstractController
{
    #[Route('', name: 'get_all', methods: ['GET'])]
    #[IsGranted(AppUserRole::ROLE_SUPER_ADMIN)]
    public function getAll(#[MapQueryString] ?SeasonQuery $query, GetAllTeamsUseCase $useCase): Response
    {
        return $useCase->execute(new GetAllTeamsCommand($query?->season));
    }

    #[Route('', name: 'create_update', methods: ['POST', 'PUT'])]
    #[IsGranted(AppUserRole::ROLE_SUPER_ADMIN)]
    public function create(
        #[MapRequestPayload] CreateUpdateTeamCommand $command,
        CreateUpdateTeamUseCase $useCase
    ): Response {
        return $useCase->execute($command);
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[IsGranted(AppUserRole::ROLE_SUPER_ADMIN)]
    public function delete(int $id, DeleteTeamUseCase $useCase): Response
    {
        return $useCase->execute(new DeleteTeamCommand($id));
    }

    #[Route('/{id}/members', name: 'update_roster', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[IsGranted(AppUserRole::ROLE_SUPER_ADMIN)]
    public function updateRoster(
        int $id,
        #[MapRequestPayload] UpdateTeamRosterInput $input,
        UpdateTeamRosterUseCase $useCase
    ): Response {
        return $useCase->execute(new UpdateTeamRosterCommand(
            $id,
            array_values($input->add),
            array_values($input->remove),
            $input->season,
        ));
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

        // run() returns a file response (stream Bunny ou fichier local), donc
        // execute() (wrapper JSON) est inutilisable : mapper les erreurs à la main
        try {
            return $useCase->run($command);
        } catch (UseCaseException $e) {
            return $this->json(['message' => $e->getMessage()], $e->getCode());
        } catch (\Throwable) {
            // e.g. fichier disparu entre la vérification du use case et la
            // construction de la réponse : garder la forme d'erreur JSON
            return $this->json(['message' => 'Unknown Error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/my-team/member/{memberId}/profile-picture', name: 'download_my_team_photo', methods: ['GET'])]
    #[IsGranted(AppUserRole::ROLE_ADMIN)]
    public function downloadMyTeamMemberPhoto(
        int $memberId,
        DownloadMyTeamMemberPhotoUseCase $useCase
    ): Response {
        /** @var AppUser $user */
        $user = $this->getUser();
        $command = new DownloadMyTeamMemberPhotoCommand($user, $memberId);

        try {
            return $useCase->run($command);
        } catch (UseCaseException $e) {
            return $this->json(['message' => $e->getMessage()], $e->getCode());
        } catch (\Throwable) {
            return $this->json(['message' => 'Unknown Error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
