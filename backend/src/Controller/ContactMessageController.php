<?php

namespace App\Controller;

use App\Application\UseCase\ContactMessage\GetAllContactMessagesUseCase;
use App\Entity\Enum\AppUserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/contact-message', name: 'api_contact_message_')]
#[IsGranted(AppUserRole::ROLE_VIEW_MESSAGE)]
class ContactMessageController extends AbstractController
{
    #[Route('', name: 'get_all', methods: ['GET'])]
    public function getAll(GetAllContactMessagesUseCase $useCase): Response
    {
        return $useCase->execute();
    }
}
