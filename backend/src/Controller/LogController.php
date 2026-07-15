<?php

namespace App\Controller;

use App\Application\UseCase\Log\ClearLogs\ClearLogsUseCase;
use App\Application\UseCase\Log\GetPaginatedLogs\GetPaginatedLogsCommand;
use App\Application\UseCase\Log\GetPaginatedLogs\GetPaginatedLogsUseCase;
use App\Entity\Enum\AppUserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Consultation des logs applicatifs (SUPER_ADMIN uniquement).
 */
#[Route('/api/logs', name: 'api_logs_')]
#[IsGranted(AppUserRole::ROLE_SUPER_ADMIN)]
class LogController extends AbstractController
{
    #[Route('/paginated', name: 'get_paginated', methods: ['GET'])]
    public function getPaginated(
        #[MapQueryString] ?GetPaginatedLogsCommand $command,
        GetPaginatedLogsUseCase $useCase
    ): Response {
        return $useCase->execute($command ?? new GetPaginatedLogsCommand());
    }

    #[Route('', name: 'clear', methods: ['DELETE'])]
    public function clear(ClearLogsUseCase $useCase): Response
    {
        return $useCase->execute();
    }
}
