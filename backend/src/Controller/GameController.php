<?php

namespace App\Controller;

use App\Application\UseCase\Game\CreateUpdateGame\CreateUpdateGameCommand;
use App\Application\UseCase\Game\CreateUpdateGame\CreateUpdateGameUseCase;
use App\Application\UseCase\Game\DeleteGame\DeleteGameCommand;
use App\Application\UseCase\Game\DeleteGame\DeleteGameUseCase;
use App\Application\UseCase\Game\GetGameHistory\GetGameHistoryCommand;
use App\Application\UseCase\Game\GetGameHistory\GetGameHistoryUseCase;
use App\Application\UseCase\Game\GetGames\GetGamesCommand;
use App\Application\UseCase\Game\GetGames\GetGamesUseCase;
use App\Application\UseCase\Game\ImportGames\ImportGamesCommand;
use App\Application\UseCase\Game\ImportGames\ImportGamesUseCase;
use App\Controller\Input\CreateUpdateGameInput;
use App\Controller\Input\GetGamesInput;
use App\Entity\AppUser;
use App\Entity\Enum\AppUserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;

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

    /**
     * Historique des transactions sur les matchs (super admin uniquement).
     * Paginé, filtrable par match (gameId) ou équipe (teamId).
     */
    #[Route('/history', name: 'history', methods: ['GET'])]
    #[IsGranted(AppUserRole::ROLE_SUPER_ADMIN)]
    public function history(
        #[MapQueryString] GetGameHistoryCommand $command,
        GetGameHistoryUseCase $useCase
    ): Response {
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

    /**
     * Importe des matchs depuis un fichier CSV (super admin uniquement).
     *
     * Le fichier doit être envoyé en multipart/form-data sous la clé "file".
     * Format CSV détaillé dans ImportGamesUseCase.
     */
    #[Route('/import', name: 'import', methods: ['POST'])]
    #[IsGranted(AppUserRole::ROLE_SUPER_ADMIN)]
    public function import(
        #[MapUploadedFile([
            new Assert\NotNull(message: 'Aucun fichier reçu. Envoyez le CSV sous la clé "file".'),
            new Assert\File(
                maxSize: '3M',
                mimeTypes: ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
                maxSizeMessage: 'Le fichier ne doit pas dépasser 3 Mo (taille reçue : {{ size }} {{ suffix }}).',
                mimeTypesMessage: 'Le fichier doit être un CSV (type MIME reçu : {{ type }}).',
                extensions: ['csv'],
                extensionsMessage: 'Seuls les fichiers .csv sont acceptés.',
            ),
        ])]
        UploadedFile $file,
        ImportGamesUseCase $useCase,
    ): Response {
        /** @var AppUser $user */
        $user = $this->getUser();

        $csvContent = file_get_contents($file->getPathname());
        if ($csvContent === false) {
            return new JsonResponse(['message' => 'Impossible de lire le fichier uploadé.'], Response::HTTP_BAD_REQUEST);
        }

        $command = new ImportGamesCommand(user: $user, csvContent: $csvContent);

        return $useCase->execute($command);
    }
}
