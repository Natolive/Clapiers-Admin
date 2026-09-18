<?php

namespace App\Controller;

use App\Application\UseCase\Member\DownloadMemberPhoto\DownloadMemberPhotoCommand;
use App\Application\UseCase\Member\DownloadMemberPhoto\DownloadMemberPhotoUseCase;
use App\Common\Exception\UseCaseException;
use App\Entity\AppUser;
use App\Entity\Enum\AppUserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Hors de MemberController, dont la classe entière est en ROLE_SUPER_ADMIN :
 * un `#[IsGranted]` de méthode s'ajoute à celui de la classe au lieu de le
 * remplacer, si bien qu'un ROLE_ADMIN y prenait 403 malgré l'attribut posé sur
 * la route. Le tri fin (coach limité à ses équipes) est dans le use case.
 */
#[Route('/api/member', name: 'api_member_photo_')]
#[IsGranted(AppUserRole::ROLE_ADMIN)]
class MemberPhotoController extends AbstractController
{
    #[Route('/{id}/profile-picture', name: 'profile_picture', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function profilePicture(int $id, DownloadMemberPhotoUseCase $useCase): Response
    {
        /** @var AppUser $user */
        $user = $this->getUser();

        // run() renvoie un flux de fichier, donc execute() (wrapper JSON) est
        // inutilisable : mapper les erreurs à la main.
        try {
            return $useCase->run(new DownloadMemberPhotoCommand($user, $id));
        } catch (UseCaseException $e) {
            return $this->json(['message' => $e->getMessage()], $e->getCode());
        } catch (\Throwable) {
            return $this->json(['message' => 'Unknown Error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
