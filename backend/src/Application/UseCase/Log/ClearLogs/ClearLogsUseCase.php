<?php

namespace App\Application\UseCase\Log\ClearLogs;

use App\Common\Command\CommandInterface;
use App\Common\UseCase\AbstractUseCase;
use App\Repository\LogRepository;

/**
 * Purge complète des logs (bouton « Vider » de la page admin).
 *
 * @extends AbstractUseCase<null>
 */
class ClearLogsUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly LogRepository $logRepository,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        return ['deleted' => $this->logRepository->deleteAll()];
    }
}
