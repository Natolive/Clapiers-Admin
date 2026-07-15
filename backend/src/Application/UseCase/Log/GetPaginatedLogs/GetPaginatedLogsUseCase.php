<?php

namespace App\Application\UseCase\Log\GetPaginatedLogs;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Log;
use App\Repository\LogRepository;

/**
 * @extends AbstractUseCase<GetPaginatedLogsCommand>
 */
class GetPaginatedLogsUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly LogRepository $logRepository,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof GetPaginatedLogsCommand) {
            throw new UseCaseException('Invalid command');
        }

        $logs = $this->logRepository->findPaginated($command->page, $command->limit, $command->level, $command->search);
        $total = $this->logRepository->countByFilters($command->level, $command->search);

        return [
            'data' => array_map(fn (Log $log) => $log->toArray(), $logs),
            'total' => $total,
        ];
    }
}
