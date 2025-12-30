<?php

namespace App\Controller;

use App\Application\UseCase\Seasons\GetActualSeasonUseCase;
use App\Application\UseCase\Seasons\GetAllSeasonsUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/season', name: 'api_season_')]
class SeasonController extends AbstractController
{
    #[Route('', name: 'get_all', methods: ['GET'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function getAll(GetAllSeasonsUseCase $useCase): Response
    {
        return $useCase->execute();
    }

    #[Route('/actual', name: 'get_actual', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getActual(GetActualSeasonUseCase $useCase): Response
    {
        return $useCase->execute();
    }
}
