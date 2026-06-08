<?php

namespace App\Controller;

use App\Application\UseCase\ContactMessage\CreateContactMessage\CreateContactMessageCommand;
use App\Application\UseCase\ContactMessage\CreateContactMessage\CreateContactMessageUseCase;
use App\Entity\Enum\MemberNationality;
use App\Repository\GameRepository;
use App\Repository\SalleClosureRepository;
use App\Repository\TeamRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public', name: 'api_public_')]
class PublicController extends AbstractController
{
    #[Route('/contact-message', name: 'contact_message_create', methods: ['POST'])]
    public function createContactMessage(
        #[MapRequestPayload] CreateContactMessageCommand $command,
        CreateContactMessageUseCase $useCase
    ): Response {
        return $useCase->execute($command);
    }

    #[Route('/home-games', name: 'home_games', methods: ['GET'])]
    public function homeGames(GameRepository $gameRepository): Response
    {
        $games = $gameRepository->findUpcomingHomeGames(10);

        return $this->json(array_map(fn ($g) => $g->toArray(), $games));
    }

    #[Route('/nationalities', name: 'nationalities', methods: ['GET'])]
    public function nationalities(): Response
    {
        return $this->json(MemberNationality::values());
    }

    #[Route('/closures', name: 'closures', methods: ['GET'])]
    public function closures(SalleClosureRepository $salleClosureRepository): Response
    {
        $closures = $salleClosureRepository->findAllOrderedByDate();

        return $this->json(array_map(fn ($c) => $c->toArray(), $closures));
    }

    #[Route('/teams', name: 'teams', methods: ['GET'])]
    public function teams(TeamRepository $teamRepository): Response
    {
        $teams = $teamRepository->findBy([], ['name' => 'ASC']);

        return $this->json(array_map(fn ($t) => $t->toArray(), $teams));
    }

    #[Route('/games', name: 'games', methods: ['GET'])]
    public function games(
        #[MapQueryString] Input\GetGamesInput $input,
        GameRepository $gameRepository
    ): Response {
        // Public endpoint: never serve an unbounded range (missing or invalid
        // params fall back to a +/- 1 year window around today)
        $start = $this->parseDate($input->start) ?? new \DateTimeImmutable('-1 year');
        $end = $this->parseDate($input->end) ?? new \DateTimeImmutable('+1 year');

        $games = $gameRepository->findAllByDateRange($start->format('Y-m-d'), $end->format('Y-m-d'));

        return $this->json(array_map(fn ($g) => $g->toArray(), $games));
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', substr($value, 0, 10));

        return $date ?: null;
    }
}
