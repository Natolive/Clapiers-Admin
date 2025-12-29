<?php

namespace App\Controller;

use App\Entity\AppUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Response;
use \Symfony\Component\Routing\Attribute\Route;

#[Route('/api/user', name: 'api_user_')]
class UserController extends AbstractController
{

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): Response
    {
        /** @var AppUser $user */
        $user = $this->getUser();
        return $this->json([
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }
}
