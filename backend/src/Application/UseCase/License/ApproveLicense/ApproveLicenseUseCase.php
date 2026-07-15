<?php

namespace App\Application\UseCase\License\ApproveLicense;

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
 * @extends AbstractUseCase<ApproveLicenseCommand>
 */
class ApproveLicenseUseCase extends AbstractUseCase
{
    private const TOKEN_VALIDITY = 'P30D';

    public function __construct(
        private readonly LicenseRepository $licenseRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'CONTACT_SENDER_EMAIL')]
        private readonly string $senderEmail,
        #[Autowire(env: 'APP_FRONTEND_URL')]
        private readonly string $frontendUrl,
    ) {
    }

    public function run(?CommandInterface $command = null): License
    {
        if (!$command instanceof ApproveLicenseCommand) {
            throw new UseCaseException('Invalid command');
        }

        $license = $this->licenseRepository->find($command->id);
        if (!$license) {
            throw new UseCaseException('Licence introuvable', Response::HTTP_NOT_FOUND);
        }

        if ($license->getStatus() !== LicenseStatus::SOUMISE) {
            throw new UseCaseException('Cette demande a déjà été traitée.', Response::HTTP_CONFLICT);
        }

        $license->setHelloAssoTierId($command->helloAssoTierId);
        $license->setAmount($command->amount);
        $license->setStatus(LicenseStatus::VALIDEE);
        $license->setApprovedAt(new \DateTimeImmutable('now'));

        if ($license->getAccessToken() === null) {
            $license->setAccessToken(bin2hex(random_bytes(32)));
        }
        $license->setTokenExpiresAt((new \DateTimeImmutable('now'))->add(new \DateInterval(self::TOKEN_VALIDITY)));

        $license->getMember()->setStatus(MemberStatus::ACTIVE);

        $this->entityManager->flush();

        $this->sendPaymentLinkEmail($license);

        return $license;
    }

    private function sendPaymentLinkEmail(License $license): void
    {
        $member = $license->getMember();
        $paymentUrl = rtrim($this->frontendUrl, '/').'/licence/'.$license->getAccessToken();
        $amountEuros = number_format(($license->getAmount() ?? 0) / 100, 2, ',', ' ');

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, 'Clapiers Volley-Ball'))
            ->to(new Address($member->getEmail(), trim($member->getFirstName().' '.$member->getLastName())))
            ->subject('Votre licence est validée — réglez votre adhésion')
            ->htmlTemplate('emails/license_approved.html.twig')
            ->context([
                'firstName' => $member->getFirstName(),
                'season' => $license->getSeason(),
                'amount' => $amountEuros,
                'paymentUrl' => $paymentUrl,
            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send license approval email', ['exception' => $e, 'licenseId' => $license->getId()]);
        }
    }
}
