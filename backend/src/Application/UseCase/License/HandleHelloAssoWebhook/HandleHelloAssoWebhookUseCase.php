<?php

namespace App\Application\UseCase\License\HandleHelloAssoWebhook;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\HelloAsso\HelloAssoClientInterface;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Enum\LicenseStatus;
use App\Entity\Enum\PaymentState;
use App\Entity\License;
use App\Entity\Payment;
use App\Repository\LicenseRepository;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Traite une notification HelloAsso. Source de vérité du paiement.
 *
 * L'URL de notification est globale à l'organisation : on ne traite que nos
 * paiements (filtrage sur metadata.licenseId) et on ignore le reste en 200.
 * On ne fait jamais confiance au payload : l'état est reconfirmé via
 * GET /checkout-intents/{id}. Le traitement est idempotent (clé = paymentId
 * HelloAsso) car HelloAsso réémet la notification jusqu'à 48 h.
 *
 * @extends AbstractUseCase<HandleHelloAssoWebhookCommand>
 */
class HandleHelloAssoWebhookUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly LicenseRepository $licenseRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly HelloAssoClientInterface $helloAssoClient,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'CONTACT_SENDER_EMAIL')]
        private readonly string $senderEmail,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof HandleHelloAssoWebhookCommand) {
            throw new UseCaseException('Invalid command');
        }

        $payload = $command->payload;

        if (($payload['eventType'] ?? null) !== 'Payment') {
            return ['status' => 'ignored'];
        }

        $metadata = $payload['metadata'] ?? ($payload['data']['metadata'] ?? []);
        $licenseId = $metadata['licenseId'] ?? null;
        if ($licenseId === null) {
            return ['status' => 'ignored'];
        }

        $license = $this->licenseRepository->find($licenseId);
        if (!$license) {
            return ['status' => 'ignored'];
        }

        $data = $payload['data'] ?? [];
        $helloAssoPaymentId = isset($data['id']) ? (int) $data['id'] : null;
        if ($helloAssoPaymentId === null) {
            return ['status' => 'ignored'];
        }

        $existing = $this->paymentRepository->findOneByHelloAssoPaymentId($helloAssoPaymentId);
        if ($existing && $existing->getState() === PaymentState::AUTHORIZED) {
            return ['status' => 'already_processed'];
        }

        $payment = $this->paymentRepository->findWaitingByLicense($license);
        if (!$payment || $payment->getHelloAssoCheckoutIntentId() === null) {
            return ['status' => 'ignored'];
        }

        // Ne pas se fier au payload : reconfirmer l'état auprès de HelloAsso.
        $intent = $this->helloAssoClient->getCheckoutIntent($payment->getHelloAssoCheckoutIntentId());
        $intentPayment = $intent['payment'] ?? [];
        $state = $intentPayment['state'] ?? null;

        $payment->setHelloAssoPaymentId($helloAssoPaymentId);
        $payment->setHelloAssoOrderId(isset($data['order']['id']) ? (int) $data['order']['id'] : null);
        $payment->setRawPayload($payload);

        if ($state === 'Authorized') {
            $payment->setState(PaymentState::AUTHORIZED);
            $payment->setPaymentReceiptUrl($intentPayment['paymentReceiptUrl'] ?? null);
            $license->setStatus(LicenseStatus::PAYEE);

            $this->entityManager->flush();
            $this->sendReceiptEmail($license);

            return ['status' => 'processed'];
        }

        if ($state === 'Refused') {
            $payment->setState(PaymentState::REFUSED);
            $license->setStatus(LicenseStatus::VALIDEE);
            $this->entityManager->flush();

            return ['status' => 'refused'];
        }

        return ['status' => 'ignored'];
    }

    private function sendReceiptEmail(License $license): void
    {
        $member = $license->getMember();
        $amountEuros = number_format(($license->getAmount() ?? 0) / 100, 2, ',', ' ');

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, 'Clapiers Volley-Ball'))
            ->to(new Address($member->getEmail(), trim($member->getFirstName().' '.$member->getLastName())))
            ->subject('Votre licence est réglée — merci !')
            ->htmlTemplate('emails/license_paid.html.twig')
            ->context([
                'firstName' => $member->getFirstName(),
                'season' => $license->getSeason(),
                'amount' => $amountEuros,
            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send license receipt email', ['exception' => $e, 'licenseId' => $license->getId()]);
        }
    }
}
