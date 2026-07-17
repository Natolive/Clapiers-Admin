<?php

namespace App\Controller;

use App\Application\UseCase\Setting\GetCurrentSeason\GetCurrentSeasonUseCase;
use App\Application\UseCase\Setting\GetInscriptionsStatus\GetInscriptionsStatusUseCase;
use App\Application\UseCase\Setting\SetCurrentSeason\SetCurrentSeasonCommand;
use App\Application\UseCase\Setting\SetCurrentSeason\SetCurrentSeasonUseCase;
use App\Application\UseCase\Setting\SetInscriptionsStatus\SetInscriptionsStatusCommand;
use App\Application\UseCase\Setting\SetInscriptionsStatus\SetInscriptionsStatusUseCase;
use App\Entity\Enum\AppUserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/settings', name: 'api_settings_')]
#[IsGranted(AppUserRole::ROLE_SUPER_ADMIN)]
class SettingController extends AbstractController
{
    #[Route('/season', name: 'get_season', methods: ['GET'])]
    public function getSeason(GetCurrentSeasonUseCase $useCase): Response
    {
        return $useCase->execute();
    }

    #[Route('/season', name: 'set_season', methods: ['PUT'])]
    public function setSeason(
        #[MapRequestPayload] SetCurrentSeasonCommand $command,
        SetCurrentSeasonUseCase $useCase
    ): Response {
        return $useCase->execute($command);
    }

    #[Route('/inscriptions', name: 'get_inscriptions', methods: ['GET'])]
    public function getInscriptions(GetInscriptionsStatusUseCase $useCase): Response
    {
        return $useCase->execute();
    }

    #[Route('/inscriptions', name: 'set_inscriptions', methods: ['PUT'])]
    public function setInscriptions(
        #[MapRequestPayload] SetInscriptionsStatusCommand $command,
        SetInscriptionsStatusUseCase $useCase
    ): Response {
        return $useCase->execute($command);
    }
}
