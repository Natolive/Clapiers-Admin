<?php

namespace App\Controller;

use App\Application\UseCase\Season\GetActualSeasonUseCase;
use App\Application\UseCase\Season\GetAllSeasonsUseCase;
use App\Entity\Enum\AppUserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/season', name: 'api_season_')]
class SeasonController extends AbstractController
{
    #[Route('', name: 'get_all', methods: ['GET'])]
    #[IsGranted(AppUserRole::ROLE_SUPER_ADMIN)]
    public function getAll(GetAllSeasonsUseCase $useCase): Response
    {
        return $useCase->execute();
    }

    #[Route('/actual', name: 'get_actual', methods: ['GET'])]
    #[IsGranted(AppUserRole::ROLE_USER)]
    public function getActual(GetActualSeasonUseCase $useCase): Response
    {
        return $useCase->execute();
    }
}
