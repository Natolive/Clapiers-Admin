<?php

namespace App\Application\UseCase\User\RequestPasswordReset;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\AccountMailer;
use App\Common\Service\RecaptchaVerifier;
use App\Common\UseCase\AbstractUseCase;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * « Mot de passe oublié » : envoie un lien (1 h) si l'adresse correspond à un
 * compte actif. La réponse est toujours la même, que le compte existe ou non,
 * pour ne pas révéler qui est inscrit.
 *
 * Bloqué pour un compte dont l'invitation est en cours ou expirée : il n'a
 * jamais choisi de mot de passe, c'est l'invitation (relancée par le club) qui
 * l'active — un lien de reset contournerait ce contrôle.
 *
 * @extends AbstractUseCase<RequestPasswordResetCommand>
 */
class RequestPasswordResetUseCase extends AbstractUseCase
{
    public const MESSAGE = 'Si un compte correspond à cette adresse, un e-mail vient de lui être envoyé.';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly RecaptchaVerifier $recaptchaVerifier,
        private readonly AccountMailer $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof RequestPasswordResetCommand) {
            throw new UseCaseException('Invalid command');
        }

        if (!$this->recaptchaVerifier->verify($command->recaptchaToken)) {
            throw new UseCaseException('Veuillez valider le captcha.');
        }

        $user = $this->userRepository->findOneBy(['email' => $command->email]);
        if (!$user || $user->isInvitationPending() || $user->isPasswordResetCoolingDown()) {
            return ['message' => self::MESSAGE];
        }

        $user->renewPasswordReset();
        $this->entityManager->flush();

        try {
            $this->mailer->sendPasswordReset($user);
        } catch (TransportExceptionInterface $e) {
            // Silencieux pour le demandeur (ne rien révéler), visible dans les logs du club.
            $this->logger->error('Failed to send password reset email', ['exception' => $e, 'userId' => $user->getId()]);
        }

        return ['message' => self::MESSAGE];
    }
}
