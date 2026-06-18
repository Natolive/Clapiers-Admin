<?php

namespace App\Application\UseCase\Game\GetGameHistory;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Repository\GameHistoryRepository;

/**
 * @extends AbstractUseCase<GetGameHistoryCommand>
 */
class GetGameHistoryUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly GameHistoryRepository $gameHistoryRepository,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof GetGameHistoryCommand) {
            throw new UseCaseException('Invalid command');
        }

        $entries = $this->gameHistoryRepository->findPaginated(
            $command->page,
            $command->limit,
            $command->gameId,
            $command->teamId,
        );

        return [
            'data'  => array_map(fn($entry) => $entry->toArray(), $entries),
            'total' => $this->gameHistoryRepository->countFiltered($command->gameId, $command->teamId),
        ];
    }
}
