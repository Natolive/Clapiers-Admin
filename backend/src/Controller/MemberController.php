<?php

namespace App\Controller;

use App\Application\UseCase\Member\CountMembersBySeason\CountMembersBySeasonCommand;
use App\Application\UseCase\Member\CountMembersBySeason\CountMembersBySeasonUseCase;
use App\Application\UseCase\Member\CreateUpdateMember\CreateUpdateMemberCommand;
use App\Application\UseCase\Member\CreateUpdateMember\CreateUpdateMemberUseCase;
use App\Application\UseCase\Member\GetAllMembersUseCase;
use App\Application\UseCase\Member\GetMembersByTeam\GetMembersByTeamCommand;
use App\Application\UseCase\Member\GetMembersByTeam\GetMembersByTeamUseCase;
use App\Entity\Enum\AppUserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
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

    #[Route('/team/{teamId}', name: 'get_by_team', methods: ['GET'])]
    public function getByTeam(int $teamId, GetMembersByTeamUseCase $useCase): Response
    {
        $command = new GetMembersByTeamCommand($teamId);
        return $useCase->execute($command);
    }

    #[Route('/count-by-season', name: 'count_by_season', methods: ['POST'])]
    public function countBySeason(
        #[MapRequestPayload] CountMembersBySeasonCommand $command,
        CountMembersBySeasonUseCase $useCase
    ): Response {
        return $useCase->execute($command);
    }
}
