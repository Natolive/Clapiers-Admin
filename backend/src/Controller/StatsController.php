<?php

namespace App\Controller;

use App\Repository\GameRepository;
use App\Repository\MemberRepository;
use App\Repository\TeamRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/stats', name: 'api_stats_')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class StatsController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(
        MemberRepository $memberRepository,
        GameRepository $gameRepository,
        TeamRepository $teamRepository,
    ): Response {
        return $this->json([
            'members' => $memberRepository->getStats(),
            'games'   => $gameRepository->getStats(),
            'teams'   => ['total' => $teamRepository->count()],
        ]);
    }
}
