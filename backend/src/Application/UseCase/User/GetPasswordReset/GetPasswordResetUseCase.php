<?php

namespace App\Application\UseCase\User\GetPasswordReset;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\AppUser;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vue publique d'un lien « mot de passe oublié » : l'e-mail du compte, pour
 * l'afficher au-dessus du formulaire.
 *
 * @extends AbstractUseCase<GetPasswordResetCommand>
 */
class GetPasswordResetUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof GetPasswordResetCommand) {
            throw new UseCaseException('Invalid command');
        }

        return ['email' => self::findValid($this->userRepository, $command->token)->getEmail()];
    }

    /** Partagé avec ResetPassword : même 404 / 410 des deux côtés. */
    public static function findValid(UserRepository $userRepository, string $token): AppUser
    {
        $user = $userRepository->findOneBy(['passwordResetToken' => $token]);
        // Une invitation lancée après la demande prime : le lien de reset meurt
        // (renewInvitation l'efface déjà ; garde-fou si les deux coexistaient).
        if (!$user || $user->isInvitationPending()) {
            throw new UseCaseException('Lien introuvable ou déjà utilisé.', Response::HTTP_NOT_FOUND);
        }
        if ($user->isPasswordResetExpired()) {
            throw new UseCaseException(
                'Ce lien a expiré. Refaites une demande depuis la page de connexion.',
                Response::HTTP_GONE,
            );
        }

        return $user;
    }
}
