<?php

namespace App\Controller;

use App\Application\UseCase\ContactMessage\CountUnreadContactMessagesUseCase;
use App\Application\UseCase\ContactMessage\GetAllContactMessagesUseCase;
use App\Application\UseCase\ContactMessage\GetReadContactMessagesUseCase;
use App\Application\UseCase\ContactMessage\GetUnreadContactMessagesUseCase;
use App\Application\UseCase\ContactMessage\MarkMessageAsRead\MarkMessageAsReadCommand;
use App\Application\UseCase\ContactMessage\MarkMessageAsRead\MarkMessageAsReadUseCase;
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

    #[Route('/unread', name: 'get_unread', methods: ['GET'])]
    public function getUnread(GetUnreadContactMessagesUseCase $useCase): Response
    {
        return $useCase->execute();
    }

    #[Route('/read', name: 'get_read', methods: ['GET'])]
    public function getRead(GetReadContactMessagesUseCase $useCase): Response
    {
        return $useCase->execute();
    }

    #[Route('/unread/count', name: 'count_unread', methods: ['GET'])]
    public function countUnread(CountUnreadContactMessagesUseCase $useCase): Response
    {
        return $useCase->execute();
    }

    #[Route('/{id}/read', name: 'mark_as_read', methods: ['PUT'])]
    #[IsGranted(AppUserRole::ROLE_CONFIRM_MESSAGE)]
    public function markAsRead(int $id, MarkMessageAsReadUseCase $useCase): Response
    {
        $command = new MarkMessageAsReadCommand($id);
        return $useCase->execute($command);
    }
}
