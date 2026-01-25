<?php

namespace App\Controller;

use App\Application\UseCase\ContactMessage\CreateContactMessage\CreateContactMessageCommand;
use App\Application\UseCase\ContactMessage\CreateContactMessage\CreateContactMessageUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public', name: 'api_public_')]
class PublicController extends AbstractController
{
    #[Route('/contact-message', name: 'contact_message_create', methods: ['POST'])]
    public function createContactMessage(
        #[MapRequestPayload] CreateContactMessageCommand $command,
        CreateContactMessageUseCase $useCase
    ): Response {
        return $useCase->execute($command);
    }
}
