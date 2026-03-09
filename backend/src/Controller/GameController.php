<?php

namespace App\Controller;

use App\Application\UseCase\Game\CreateUpdateGame\CreateUpdateGameCommand;
use App\Application\UseCase\Game\CreateUpdateGame\CreateUpdateGameUseCase;
use App\Application\UseCase\Game\DeleteGame\DeleteGameCommand;
use App\Application\UseCase\Game\DeleteGame\DeleteGameUseCase;
use App\Application\UseCase\Game\GetGames\GetGamesCommand;
use App\Application\UseCase\Game\GetGames\GetGamesUseCase;
use App\Controller\Input\CreateUpdateGameInput;
use App\Controller\Input\GetGamesInput;
use App\Entity\AppUser;
use App\Entity\Enum\AppUserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/game', name: 'api_game_')]
class GameController extends AbstractController
{
    #[Route('', name: 'get_all', methods: ['GET'])]
    #[IsGranted(AppUserRole::ROLE_ADMIN)]
    public function getAll(
        #[MapQueryString] GetGamesInput $input,
        GetGamesUseCase $useCase
    ): Response {
        /** @var AppUser $user */
        $user = $this->getUser();

        $command = new GetGamesCommand(
            user:   $user,
            teamId: $input->teamId,
            start:  $input->start,
            end:    $input->end,
        );

        return $useCase->execute($command);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted(AppUserRole::ROLE_ADMIN)]
    public function create(
        #[MapRequestPayload] CreateUpdateGameInput $input,
        CreateUpdateGameUseCase $useCase
    ): Response {
        /** @var AppUser $user */
        $user = $this->getUser();

        $command = new CreateUpdateGameCommand(
            user:        $user,
            opponent:    $input->opponent,
            date:        $input->date,
            meetingTime: $input->meetingTime,
            venue:       $input->venue,
            location:    $input->location,
            teamId:      $input->teamId,
        );

        return $useCase->execute($command);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    #[IsGranted(AppUserRole::ROLE_ADMIN)]
    public function update(
        int $id,
        #[MapRequestPayload] CreateUpdateGameInput $input,
        CreateUpdateGameUseCase $useCase
    ): Response {
        /** @var AppUser $user */
        $user = $this->getUser();

        $command = new CreateUpdateGameCommand(
            user:        $user,
            opponent:    $input->opponent,
            date:        $input->date,
            meetingTime: $input->meetingTime,
            venue:       $input->venue,
            location:    $input->location,
            teamId:      $input->teamId,
            id:          $id,
        );

        return $useCase->execute($command);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted(AppUserRole::ROLE_ADMIN)]
    public function delete(int $id, DeleteGameUseCase $useCase): Response
    {
        /** @var AppUser $user */
        $user = $this->getUser();
        $command = new DeleteGameCommand(user: $user, id: $id);

        return $useCase->execute($command);
    }
}
