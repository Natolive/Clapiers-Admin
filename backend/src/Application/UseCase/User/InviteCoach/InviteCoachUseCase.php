<?php

namespace App\Application\UseCase\User\InviteCoach;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\CoachInvitationMailer;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\AppUser;
use App\Entity\Enum\AppUserRole;
use App\Repository\MemberRepository;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Invite un licencié comme chef d'équipe : crée (ou reprend) son compte
 * ROLE_COACH lié à sa fiche, lui affecte ses équipes et lui envoie un lien pour
 * choisir son mot de passe. Relancer l'invitation renvoie un nouveau lien et
 * remplace la liste d'équipes.
 *
 * @extends AbstractUseCase<InviteCoachCommand>
 */
class InviteCoachUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly TeamRepository $teamRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly CoachInvitationMailer $mailer,
    ) {
    }

    public function run(?CommandInterface $command = null): AppUser
    {
        if (!$command instanceof InviteCoachCommand) {
            throw new UseCaseException('Invalid command');
        }

        $member = $this->memberRepository->find($command->memberId);
        if (!$member) {
            throw new UseCaseException('Licencié introuvable', Response::HTTP_NOT_FOUND);
        }
        if (!$member->getEmail()) {
            throw new UseCaseException("Ce licencié n'a pas d'adresse e-mail.", Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $teams = [];
        foreach (array_unique($command->teamIds) as $teamId) {
            $team = $this->teamRepository->find($teamId);
            if (!$team) {
                throw new UseCaseException(sprintf('Équipe %d introuvable', $teamId), Response::HTTP_NOT_FOUND);
            }
            $teams[] = $team;
        }

        $user = $this->userRepository->findOneBy(['member' => $member]);
        if (!$user) {
            if ($this->userRepository->findOneBy(['email' => $member->getEmail()])) {
                throw new UseCaseException(
                    'Un compte existe déjà avec cette adresse e-mail : liez-le au licencié depuis la page Utilisateurs.',
                    Response::HTTP_CONFLICT,
                );
            }

            $user = (new AppUser())->setEmail($member->getEmail())->setMember($member);
            // ponytail: mot de passe aléatoire jamais communiqué — le compte reste
            // inutilisable tant que l'invitation n'est pas acceptée.
            $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
            $this->entityManager->persist($user);
        }

        // Ne jamais rétrograder un super admin qui serait aussi chef d'équipe.
        if (!in_array(AppUserRole::ROLE_SUPER_ADMIN, $user->getRoles(), true)) {
            $user->setRoles([AppUserRole::ROLE_COACH]);
        }
        $user->setTeams($teams);
        $user->renewInvitation();

        $this->entityManager->flush();

        try {
            $this->mailer->send($user);
        } catch (TransportExceptionInterface) {
            throw new UseCaseException(
                "Le compte est prêt mais l'e-mail n'a pas pu partir. Réessayez l'invitation.",
                Response::HTTP_BAD_GATEWAY,
            );
        }

        return $user;
    }
}
