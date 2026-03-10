<?php

namespace App\Controller;

use App\Application\UseCase\ContactMessage\CreateContactMessage\CreateContactMessageCommand;
use App\Application\UseCase\ContactMessage\CreateContactMessage\CreateContactMessageUseCase;
use App\Entity\Enum\MemberNationality;
use App\Repository\GameRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
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

    #[Route('/games', name: 'games', methods: ['GET'])]
    public function games(
        #[MapQueryString] \App\Controller\Input\GetGamesInput $input,
        GameRepository $gameRepository
    ): Response {
        $games = $gameRepository->findAllByDateRange($input->start, $input->end);

        return $this->json(array_map(fn ($g) => $g->toArray(), $games));
    }
}
