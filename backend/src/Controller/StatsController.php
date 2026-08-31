<?php

namespace App\Controller;

use App\Common\Service\SeasonProvider;
use App\Controller\Input\SeasonQuery;
use App\Repository\ContactMessageRepository;
use App\Repository\GameRepository;
use App\Repository\LicenseRepository;
use App\Repository\MemberRepository;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/stats', name: 'api_stats_')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class StatsController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(
        #[MapQueryString] ?SeasonQuery $query,
        MemberRepository $memberRepository,
        GameRepository $gameRepository,
        TeamRepository $teamRepository,
        LicenseRepository $licenseRepository,
        UserRepository $userRepository,
        ContactMessageRepository $contactMessageRepository,
        SeasonProvider $seasonProvider,
    ): Response {
        // Saison choisie via ?season=AAAA-AAAA (validée) ; à défaut, la courante.
        $season = $query?->season ?: $seasonProvider->current();

        return $this->json([
            'members'  => $memberRepository->getStats($season),
            'games'    => $gameRepository->getStats(),
            'teams'    => ['total' => $teamRepository->count()],
            'licenses' => $licenseRepository->getStats($season),
            'users'    => ['total' => $userRepository->count([])],
            'messages' => ['total' => $contactMessageRepository->countBySearch()],
        ]);
    }
}
