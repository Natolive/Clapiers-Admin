<?php

namespace App\Application\UseCase\ContactMessage\CreateContactMessage;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\RecaptchaVerifier;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\ContactMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * @extends AbstractUseCase<CreateContactMessageCommand>
 */
class CreateContactMessageUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly RecaptchaVerifier $recaptchaVerifier,
        #[Autowire(env: 'CONTACT_RECIPIENT_EMAIL')]
        private readonly string $recipientEmail,
        #[Autowire(env: 'CONTACT_SENDER_EMAIL')]
        private readonly string $senderEmail,
    ) {
    }

    public function run(?CommandInterface $command = null): ContactMessage
    {
        if (!$command instanceof CreateContactMessageCommand) {
            throw new UseCaseException('Invalid command');
        }

        if (!$this->recaptchaVerifier->verify($command->recaptchaToken)) {
            throw new UseCaseException('Veuillez valider le captcha.');
        }

        $message = new ContactMessage();
        $message->setFirstName($command->firstName);
        $message->setLastName($command->lastName);
        $message->setEmail($command->email);
        $message->setSubject($command->subject);
        $message->setMessage($command->message);

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        $this->sendNotificationEmail($command);

        return $message;
    }

    private function sendNotificationEmail(CreateContactMessageCommand $command): void
    {
        $fullName = trim($command->firstName.' '.$command->lastName);
        $receivedAt = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))
            ->format('d/m/Y à H:i');

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, 'Clapiers Volley-Ball'))
            ->to($this->recipientEmail)
            ->replyTo(new Address($command->email, $fullName))
            ->subject(sprintf('[Demande de contact] %s — %s', $command->subject, $fullName))
            ->htmlTemplate('emails/contact_notification.html.twig')
            ->context([
                'fullName' => $fullName,
                'senderEmail' => $command->email,
                'subject' => $command->subject,
                'message' => $command->message,
                'receivedAt' => $receivedAt,
            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send contact notification email', [
                'exception' => $e,
                'from' => $command->email,
            ]);
        }
    }
}
