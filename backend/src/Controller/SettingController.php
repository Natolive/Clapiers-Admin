<?php

namespace App\Controller;

use App\Application\UseCase\Setting\GetCurrentSeason\GetCurrentSeasonUseCase;
use App\Application\UseCase\Setting\SetCurrentSeason\SetCurrentSeasonCommand;
use App\Application\UseCase\Setting\SetCurrentSeason\SetCurrentSeasonUseCase;
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
}
