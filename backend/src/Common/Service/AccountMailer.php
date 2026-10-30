<?php

namespace App\Common\Service;

use App\Entity\AppUser;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * E-mails du compte de connexion : invitation chef d'équipe et « mot de passe
 * oublié ». Les échecs d'envoi remontent à l'appelant, qui décide (l'invitation
 * les signale à l'admin, la réinitialisation les tait pour ne rien révéler).
 */
class AccountMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire(env: 'CONTACT_SENDER_EMAIL')]
        private readonly string $senderEmail,
        #[Autowire(env: 'APP_FRONTEND_URL')]
        private readonly string $frontendUrl,
    ) {
    }

    public function sendInvitation(AppUser $user): void
    {
        $this->send($user, 'Votre accès chef d\'équipe — Clapiers Volley-Ball', 'emails/coach_invitation.html.twig', [
            'teams' => array_map(fn ($t) => $t->getName(), $user->getTeams()->toArray()),
            'invitationUrl' => $this->url('/invitation#'.$user->getInvitationToken()),
        ]);
    }

    public function sendPasswordReset(AppUser $user): void
    {
        $this->send($user, 'Réinitialisation de votre mot de passe — Clapiers Volley-Ball', 'emails/password_reset.html.twig', [
            'resetUrl' => $this->url('/mot-de-passe#'.$user->getPasswordResetToken()),
        ]);
    }

    private function send(AppUser $user, string $subject, string $template, array $context): void
    {
        $member = $user->getMember();

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, 'Clapiers Volley-Ball'))
            ->to(new Address($user->getEmail(), trim(($member?->getFirstName() ?? '').' '.($member?->getLastName() ?? ''))))
            ->subject($subject)
            ->htmlTemplate($template)
            ->context(['firstName' => $member?->getFirstName(), ...$context]);

        $this->mailer->send($email);
    }

    /**
     * Le jeton passe dans le fragment (#) : jamais envoyé au serveur, ni dans le
     * Referer, ni collecté par Google Tag Manager (chargé sur tout le site).
     */
    private function url(string $path): string
    {
        return rtrim($this->frontendUrl, '/').$path;
    }
}
