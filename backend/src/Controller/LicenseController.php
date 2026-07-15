<?php

namespace App\Controller;

use App\Application\UseCase\License\ApproveLicense\ApproveLicenseCommand;
use App\Application\UseCase\License\ApproveLicense\ApproveLicensePayload;
use App\Application\UseCase\License\ApproveLicense\ApproveLicenseUseCase;
use App\Application\UseCase\License\GetLicenseTiers\GetLicenseTiersUseCase;
use App\Application\UseCase\License\GetPaginatedLicenses\GetPaginatedLicensesCommand;
use App\Application\UseCase\License\GetPaginatedLicenses\GetPaginatedLicensesUseCase;
use App\Application\UseCase\License\RejectLicense\RejectLicenseCommand;
use App\Application\UseCase\License\RejectLicense\RejectLicensePayload;
use App\Application\UseCase\License\RejectLicense\RejectLicenseUseCase;
use App\Entity\Enum\AppUserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/license', name: 'api_license_')]
#[IsGranted(AppUserRole::ROLE_SUPER_ADMIN)]
class LicenseController extends AbstractController
{
    #[Route('/paginated', name: 'get_paginated', methods: ['GET'])]
    public function getPaginated(
        #[MapQueryString] ?GetPaginatedLicensesCommand $command,
        GetPaginatedLicensesUseCase $useCase
    ): Response {
        return $useCase->execute($command ?? new GetPaginatedLicensesCommand());
    }

    #[Route('/tiers', name: 'get_tiers', methods: ['GET'])]
    public function getTiers(GetLicenseTiersUseCase $useCase): Response
    {
        return $useCase->execute();
    }

    #[Route('/{id}/approve', name: 'approve', methods: ['POST'])]
    public function approve(
        int $id,
        #[MapRequestPayload] ApproveLicensePayload $payload,
        ApproveLicenseUseCase $useCase
    ): Response {
        return $useCase->execute(new ApproveLicenseCommand($id, $payload->helloAssoTierId, $payload->amount));
    }

    #[Route('/{id}/reject', name: 'reject', methods: ['POST'])]
    public function reject(
        int $id,
        #[MapRequestPayload] RejectLicensePayload $payload,
        RejectLicenseUseCase $useCase
    ): Response {
        return $useCase->execute(new RejectLicenseCommand($id, $payload->reason));
    }
}
