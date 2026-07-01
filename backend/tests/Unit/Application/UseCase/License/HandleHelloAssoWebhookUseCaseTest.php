<?php

namespace App\Tests\Unit\Application\UseCase\License;

use App\Application\UseCase\License\HandleHelloAssoWebhook\HandleHelloAssoWebhookCommand;
use App\Application\UseCase\License\HandleHelloAssoWebhook\HandleHelloAssoWebhookUseCase;
use App\Common\Service\HelloAsso\HelloAssoClientInterface;
use App\Entity\Enum\LicenseStatus;
use App\Entity\Enum\PaymentState;
use App\Entity\License;
use App\Entity\Member;
use App\Entity\Payment;
use App\Repository\LicenseRepository;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;

class HandleHelloAssoWebhookUseCaseTest extends TestCase
{
    public function testConfirmationSurvivesReceiptMailerOutage(): void
    {
        $member = (new Member())->setFirstName('Marie')->setLastName('Curie')->setEmail('marie@test.fr');
        $license = (new License())->setMember($member)->setSeason('2026-2027')->setAmount(12000)->setStatus(LicenseStatus::EN_PAIEMENT);
        $payment = (new Payment())->setLicense($license)->setAmount(12000)->setState(PaymentState::WAITING)->setHelloAssoCheckoutIntentId(999001);

        $licenseRepository = $this->createStub(LicenseRepository::class);
        $licenseRepository->method('find')->willReturn($license);

        $paymentRepository = $this->createStub(PaymentRepository::class);
        $paymentRepository->method('findOneByHelloAssoPaymentId')->willReturn(null);
        $paymentRepository->method('findWaitingByLicense')->willReturn($payment);

        $client = $this->createStub(HelloAssoClientInterface::class);
        $client->method('getCheckoutIntent')->willReturn(['payment' => ['state' => 'Authorized', 'paymentReceiptUrl' => 'https://receipt']]);

        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willThrowException(new TransportException('SMTP down'));

        $useCase = new HandleHelloAssoWebhookUseCase(
            $licenseRepository,
            $paymentRepository,
            $this->createStub(EntityManagerInterface::class),
            $client,
            $mailer,
            new NullLogger(),
            'club@test.fr',
        );

        $result = $useCase->run(new HandleHelloAssoWebhookCommand([
            'eventType' => 'Payment',
            'data' => ['id' => 777001, 'order' => ['id' => 888001]],
            'metadata' => ['licenseId' => 1],
        ]));

        $this->assertSame('processed', $result['status']);
        $this->assertSame(LicenseStatus::PAYEE, $license->getStatus());
        $this->assertSame(PaymentState::AUTHORIZED, $payment->getState());
    }
}
