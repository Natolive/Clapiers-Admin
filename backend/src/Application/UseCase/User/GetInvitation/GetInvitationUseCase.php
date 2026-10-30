<?php

namespace App\Application\UseCase\User\GetInvitation;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\AppUser;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vue publique d'une invitation (par lien) : de quoi accueillir l'invité,
 * rien de plus.
 *
 * @extends AbstractUseCase<GetInvitationCommand>
 */
class GetInvitationUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof GetInvitationCommand) {
            throw new UseCaseException('Invalid command');
        }

        $user = self::findValid($this->userRepository, $command->token);

        return [
            'email' => $user->getEmail(),
            'firstName' => $user->getMember()?->getFirstName(),
        ];
    }

    /** Partagé avec l'acceptation : même 404 / 410 des deux côtés. */
    public static function findValid(UserRepository $userRepository, string $token): AppUser
    {
        $user = $userRepository->findOneBy(['invitationToken' => $token]);
        if (!$user) {
            throw new UseCaseException('Invitation introuvable ou déjà utilisée.', Response::HTTP_NOT_FOUND);
        }
        if ($user->isInvitationExpired()) {
            throw new UseCaseException(
                'Cette invitation a expiré. Demandez au club de vous en renvoyer une.',
                Response::HTTP_GONE,
            );
        }

        return $user;
    }
}
