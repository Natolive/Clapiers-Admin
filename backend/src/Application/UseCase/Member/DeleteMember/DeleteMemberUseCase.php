<?php

namespace App\Application\UseCase\Member\DeleteMember;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Repository\LicenseRepository;
use App\Repository\MemberRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suppression douce d'un licencié.
 *
 * `remove()` ne supprime rien : l'écouteur SoftDeleteable de
 * gedmo/doctrine-extensions intercepte la suppression et horodate `$deletedAt`
 * à la place. Paiements, médiathèque et fichiers stockés restent donc intacts
 * et récupérables.
 *
 * Les licences sont supprimées en douceur avec le membre — Gedmo ne cascade
 * pas tout seul. C'est indispensable : une licence est atteignable par son
 * propre `accessToken` (magic link public), sans passer par le membre.
 *
 * Le compte utilisateur éventuellement lié est supprimé en douceur lui aussi :
 * un licencié supprimé ne doit plus pouvoir se connecter. Le lien membre est
 * conservé, ce qui rend la restauration du couple possible.
 *
 * Le filtre `softdeleteable` masquant les lignes supprimées, `find()` ne
 * retrouve pas un membre déjà supprimé : une seconde suppression répond 404.
 *
 * @extends AbstractUseCase<DeleteMemberCommand>
 */
class DeleteMemberUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly LicenseRepository $licenseRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{id: int, deleted: true}
     */
    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof DeleteMemberCommand) {
            throw new UseCaseException('Invalid command');
        }

        $member = $this->memberRepository->find($command->id);
        if (!$member) {
            throw new UseCaseException('Member not found', Response::HTTP_NOT_FOUND);
        }

        // Cascade explicite : Gedmo ne propage pas la suppression douce. Sans
        // elle, les licences survivraient en pointant vers un membre masqué, et
        // le parcours public de paiement répondrait 500 au lieu de 404.
        foreach ($this->licenseRepository->findBy(['member' => $member]) as $license) {
            $this->entityManager->remove($license);
        }

        // Le compte lié part avec le licencié : un licencié supprimé ne doit
        // plus retrouver son compte. Le provider de sécurité passant par l'ORM,
        // le filtre le masque et l'authentification échoue — y compris avec un
        // JWT déjà émis, l'utilisateur étant rechargé à chaque requête.
        $account = $this->userRepository->findOneBy(['member' => $member]);
        if ($account) {
            $this->entityManager->remove($account);
        }

        $this->entityManager->remove($member);
        $this->entityManager->flush();

        return ['id' => $command->id, 'deleted' => true];
    }
}
