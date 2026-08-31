<?php

namespace App\Common\Service;

use App\Entity\License;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * E-mail « votre licence est validée, voici le lien de paiement ». Envoyé à
 * l'approbation puis à chaque renvoi du lien — un seul endroit qui construit
 * l'URL du magic link. Un échec d'envoi est journalisé, jamais propagé : il ne
 * doit pas annuler la validation.
 */
class LicensePaymentLinkMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'CONTACT_SENDER_EMAIL')]
        private readonly string $senderEmail,
        #[Autowire(env: 'APP_FRONTEND_URL')]
        private readonly string $frontendUrl,
    ) {
    }

    public function send(License $license): void
    {
        $member = $license->getMember();

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, 'Clapiers Volley-Ball'))
            ->to(new Address($member->getEmail(), trim($member->getFirstName().' '.$member->getLastName())))
            ->subject('Votre licence est validée — réglez votre adhésion')
            ->htmlTemplate('emails/license_approved.html.twig')
            ->context([
                'firstName' => $member->getFirstName(),
                'season' => $license->getSeason(),
                'amount' => number_format(($license->getAmount() ?? 0) / 100, 2, ',', ' '),
                'paymentUrl' => rtrim($this->frontendUrl, '/').'/licence/'.$license->getAccessToken(),
            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send license payment link email', [
                'exception' => $e,
                'licenseId' => $license->getId(),
            ]);
        }
    }
}
