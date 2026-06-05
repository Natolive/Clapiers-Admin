<?php

namespace App\Controller;

use App\Application\UseCase\ContactMessage\GetPaginatedContactMessages\GetPaginatedContactMessagesCommand;
use App\Application\UseCase\ContactMessage\GetPaginatedContactMessages\GetPaginatedContactMessagesUseCase;
use App\Entity\Enum\AppUserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/contact-message', name: 'api_contact_message_')]
#[IsGranted(AppUserRole::ROLE_VIEW_MESSAGE)]
class ContactMessageController extends AbstractController
{
    #[Route('', name: 'get_all', methods: ['GET'])]
    public function getAll(
        #[MapQueryString] GetPaginatedContactMessagesCommand $command,
        GetPaginatedContactMessagesUseCase $useCase
    ): Response {
        return $useCase->execute($command);
    }
}
