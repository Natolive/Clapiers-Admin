<?php

namespace App\Controller;

use App\Application\UseCase\SalleClosure\CreateSalleClosure\CreateSalleClosureCommand;
use App\Application\UseCase\SalleClosure\CreateSalleClosure\CreateSalleClosureUseCase;
use App\Application\UseCase\SalleClosure\DeleteSalleClosure\DeleteSalleClosureCommand;
use App\Application\UseCase\SalleClosure\DeleteSalleClosure\DeleteSalleClosureUseCase;
use App\Application\UseCase\SalleClosure\GetSalleClosures\GetSalleClosuresUseCase;
use App\Controller\Input\CreateSalleClosureInput;
use App\Entity\Enum\AppUserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/salle-closure', name: 'api_salle_closure_')]
class SalleClosureController extends AbstractController
{
    /** Any authenticated user sees closures on their calendar */
    #[Route('', name: 'get_all', methods: ['GET'])]
    #[IsGranted(AppUserRole::ROLE_USER)]
    public function getAll(GetSalleClosuresUseCase $useCase): Response
    {
        return $useCase->execute();
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted(AppUserRole::ROLE_SUPER_ADMIN)]
    public function create(
        #[MapRequestPayload] CreateSalleClosureInput $input,
        CreateSalleClosureUseCase $useCase
    ): Response {
        $command = new CreateSalleClosureCommand(
            startDate: $input->startDate,
            endDate:   $input->endDate,
            reason:    $input->reason,
        );

        return $useCase->execute($command);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted(AppUserRole::ROLE_SUPER_ADMIN)]
    public function delete(int $id, DeleteSalleClosureUseCase $useCase): Response
    {
        return $useCase->execute(new DeleteSalleClosureCommand($id));
    }
}
