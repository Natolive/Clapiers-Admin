<?php

namespace App\Application\UseCase\License\RejectLicense;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Enum\LicenseStatus;
use App\Entity\Enum\MemberStatus;
use App\Entity\License;
use App\Repository\LicenseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * @extends AbstractUseCase<RejectLicenseCommand>
 */
class RejectLicenseUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly LicenseRepository $licenseRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'CONTACT_SENDER_EMAIL')]
        private readonly string $senderEmail,
    ) {
    }

    public function run(?CommandInterface $command = null): License
    {
        if (!$command instanceof RejectLicenseCommand) {
            throw new UseCaseException('Invalid command');
        }

        $license = $this->licenseRepository->find($command->id);
        if (!$license) {
            throw new UseCaseException('Licence introuvable', Response::HTTP_NOT_FOUND);
        }

        if ($license->getStatus() !== LicenseStatus::SOUMISE) {
            throw new UseCaseException('Cette demande a déjà été traitée.', Response::HTTP_CONFLICT);
        }

        $license->setStatus(LicenseStatus::REFUSEE);
        $license->setRejectionReason($command->reason);
        $license->getMember()->setStatus(MemberStatus::REJECTED);

        $this->entityManager->flush();

        $this->sendRejectionEmail($license);

        return $license;
    }

    private function sendRejectionEmail(License $license): void
    {
        $member = $license->getMember();

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, 'Clapiers Volley-Ball'))
            ->to(new Address($member->getEmail(), trim($member->getFirstName().' '.$member->getLastName())))
            ->subject('Votre demande de licence')
            ->htmlTemplate('emails/license_rejected.html.twig')
            ->context([
                'firstName' => $member->getFirstName(),
                'reason' => $license->getRejectionReason(),
            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send license rejection email', ['exception' => $e, 'licenseId' => $license->getId()]);
        }
    }
}
