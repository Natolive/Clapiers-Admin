<?php

namespace App\Controller;

use App\Application\UseCase\Member\CreateUpdateMember\CreateUpdateMemberCommand;
use App\Application\UseCase\Member\CreateUpdateMember\CreateUpdateMemberUseCase;
use App\Application\UseCase\Member\DeleteMember\DeleteMemberCommand;
use App\Application\UseCase\Member\DeleteMember\DeleteMemberUseCase;
use App\Application\UseCase\Member\ExportMembers\ExportMembersCommand;
use App\Application\UseCase\Member\ExportMembers\ExportMembersUseCase;
use App\Application\UseCase\Member\GetAllMembersUseCase;
use App\Application\UseCase\Member\GetMembersByTeam\GetMembersByTeamCommand;
use App\Application\UseCase\Member\GetMembersByTeam\GetMembersByTeamUseCase;
use App\Application\UseCase\Member\GetPaginatedMembers\GetPaginatedMembersCommand;
use App\Application\UseCase\Member\GetPaginatedMembers\GetPaginatedMembersUseCase;
use App\Application\UseCase\Member\SetFsgtRegistration\SetFsgtRegistrationCommand;
use App\Application\UseCase\Member\SetFsgtRegistration\SetFsgtRegistrationUseCase;
use App\Common\Exception\UseCaseException;
use App\Common\Service\MemberMediaStorage;
use App\Controller\Input\SeasonQuery;
use App\Controller\Input\SetFsgtRegistrationInput;
use App\Entity\Enum\AppUserRole;
use App\Repository\MemberDocumentRepository;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/member', name: 'api_member_')]
#[IsGranted(AppUserRole::ROLE_SUPER_ADMIN)]
class MemberController extends AbstractController
{
    #[Route('', name: 'get_all', methods: ['GET'])]
    public function getAll(GetAllMembersUseCase $useCase): Response
    {
        return $useCase->execute();
    }

    #[Route('', name: 'create_update', methods: ['POST', 'PUT'])]
    public function create(
        #[MapRequestPayload] CreateUpdateMemberCommand $command,
        CreateUpdateMemberUseCase $useCase
    ): Response {
        return $useCase->execute($command);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, DeleteMemberUseCase $useCase): Response
    {
        return $useCase->execute(new DeleteMemberCommand($id));
    }

    #[Route('/paginated', name: 'get_paginated', methods: ['GET'])]
    public function getPaginated(
        #[MapQueryString] GetPaginatedMembersCommand $command,
        GetPaginatedMembersUseCase $useCase
    ): Response {
        return $useCase->execute($command);
    }

    /**
     * Export de la liste des licenciés. Mêmes filtres que /paginated (on exporte
     * ce qu'on voit), plus `columns[]` pour choisir les colonnes du tableau et
     * `files[]` les pièces de la médiathèque à joindre. Sans `columns[]`, toutes
     * les colonnes ; sans `files[]`, un xlsx nu — sinon un zip (xlsx + pièces).
     *
     * `validationFailedStatusCode` est forcé : #[MapQueryString] répond 404 par
     * défaut (contrairement à #[MapRequestPayload]), ce qui ferait passer une
     * colonne ou une saison invalide pour une route inexistante.
     */
    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ?ExportMembersCommand $command,
        ExportMembersUseCase $useCase,
    ): Response {
        // run() renvoie un fichier, donc execute() (wrapper JSON) est
        // inutilisable : mapper les erreurs à la main.
        try {
            return $useCase->run($command ?? new ExportMembersCommand());
        } catch (UseCaseException $e) {
            return $this->json(['message' => $e->getMessage()], $e->getCode());
        } catch (\Throwable) {
            return $this->json(['message' => 'Unknown Error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Déclare l'inscription FSGT du licencié pour une saison (celle en cours par
     * défaut). L'information est portée par la licence de la saison.
     */
    #[Route('/{id}/fsgt', name: 'set_fsgt', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function setFsgtRegistration(
        int $id,
        #[MapRequestPayload] SetFsgtRegistrationInput $input,
        SetFsgtRegistrationUseCase $useCase,
    ): Response {
        return $useCase->execute(new SetFsgtRegistrationCommand(
            $id,
            $input->registered,
            $input->licenseNumber,
            $input->season,
        ));
    }

    #[Route('/team/{teamId}', name: 'get_by_team', methods: ['GET'])]
    public function getByTeam(int $teamId, #[MapQueryString] ?SeasonQuery $query, GetMembersByTeamUseCase $useCase): Response
    {
        $command = new GetMembersByTeamCommand($teamId, $query?->season);
        return $useCase->execute($command);
    }

    #[Route('/{id}/profile-picture', name: 'profile_picture', methods: ['GET'])]
    #[IsGranted(AppUserRole::ROLE_ADMIN)]
    public function profilePicture(
        int $id,
        MemberRepository $memberRepository,
        MemberDocumentRepository $documentRepository,
        MemberMediaStorage $mediaStorage
    ): Response {
        $member = $memberRepository->find($id);
        if (!$member) {
            return $this->json(['error' => 'Profile picture not found'], 404);
        }

        // La médiathèque est la source unique : slot « Photo d'identité ».
        $slot = $documentRepository->findRootDocumentSlot($member, 'identity_photo');
        if ($slot && $slot->hasFile()) {
            $response = $mediaStorage->response((string) $slot->getStoredName(), $slot->getMimeType());
            if ($response !== null) {
                return $response;
            }
        }

        return $this->json(['error' => 'Profile picture not found'], 404);
    }
}
