<?php

namespace App\Application\UseCase\Game\DeleteGame;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractUseCase<DeleteGameCommand>
 */
class DeleteGameUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly GameRepository         $gameRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function run(?CommandInterface $command = null): null
    {
        if (!$command instanceof DeleteGameCommand) {
            throw new UseCaseException('Invalid command');
        }

        $game = $this->gameRepository->find($command->id);
        if (!$game) {
            throw new UseCaseException('Game not found', Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($game);
        $this->entityManager->flush();

        return null;
    }
}
