<?php

namespace App\Common\Service;

use App\Entity\AppUser;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * E-mail « vous êtes invité à gérer votre équipe, choisissez votre mot de
 * passe ». Contrairement au lien de paiement, un échec d'envoi est propagé :
 * sans ce mail, l'invité n'a aucun moyen d'activer son compte.
 */
class CoachInvitationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire(env: 'CONTACT_SENDER_EMAIL')]
        private readonly string $senderEmail,
        #[Autowire(env: 'APP_FRONTEND_URL')]
        private readonly string $frontendUrl,
    ) {
    }

    public function send(AppUser $user): void
    {
        $member = $user->getMember();

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, 'Clapiers Volley-Ball'))
            ->to(new Address($user->getEmail(), trim(($member?->getFirstName() ?? '').' '.($member?->getLastName() ?? ''))))
            ->subject('Votre accès chef d\'équipe — Clapiers Volley-Ball')
            ->htmlTemplate('emails/coach_invitation.html.twig')
            ->context([
                'firstName' => $member?->getFirstName(),
                'teams' => array_map(fn ($t) => $t->getName(), $user->getTeams()->toArray()),
                'invitationUrl' => rtrim($this->frontendUrl, '/').'/invitation/'.$user->getInvitationToken(),
            ]);

        $this->mailer->send($email);
    }
}
