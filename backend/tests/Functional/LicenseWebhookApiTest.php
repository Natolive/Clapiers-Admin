<?php

namespace App\Tests\Functional;

use App\Entity\Enum\LicenseStatus;
use App\Entity\Enum\PaymentState;
use App\Entity\License;
use App\Entity\Payment;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Fake\FakeHelloAssoClient;

/**
 * Webhook HelloAsso (source de vérité). L'état est reconfirmé via le fake
 * getCheckoutIntent ; le traitement est idempotent.
 */
class LicenseWebhookApiTest extends ApiTestCase
{
    private const URL = '/api/public/helloasso/webhook';

    private function makeEnPaiement(string $token): License
    {
        $license = $this->aLicense()
            ->withToken($token)
            ->withStatus(LicenseStatus::EN_PAIEMENT)
            ->withAmount(12000)
            ->persist();

        $payment = (new Payment())
            ->setLicense($license)
            ->setAmount(12000)
            ->setState(PaymentState::WAITING)
            ->setHelloAssoCheckoutIntentId(999001);

        $this->em()->persist($payment);
        $this->em()->flush();

        return $license;
    }

    private function paymentPayload(int $licenseId): array
    {
        return [
            'eventType' => 'Payment',
            'data' => ['id' => 777001, 'amount' => 12000, 'state' => 'Authorized', 'order' => ['id' => 888001]],
            'metadata' => ['licenseId' => $licenseId, 'memberId' => 1],
        ];
    }

    public function testWebhookConfirmsPaymentAndMarksLicensePaid(): void
    {
        $license = $this->makeEnPaiement('wh-ok');
        $id = $license->getId();

        $this->postJson(self::URL, $this->paymentPayload($id));

        $this->assertSame('processed', $this->assertJsonResponse(200)['status']);

        $this->em()->clear();
        $reloaded = $this->em()->getRepository(License::class)->find($id);
        $this->assertSame(LicenseStatus::PAYEE, $reloaded->getStatus());
        $this->assertTrue($reloaded->getMember()->isLicensePaid());

        $payment = $this->em()->getRepository(Payment::class)->findOneByHelloAssoPaymentId(777001);
        $this->assertSame(PaymentState::AUTHORIZED, $payment->getState());
        $this->assertSame(888001, $payment->getHelloAssoOrderId());

        $this->assertEmailCount(1);
    }

    public function testWebhookIsIdempotent(): void
    {
        $license = $this->makeEnPaiement('wh-idem');
        $payload = $this->paymentPayload($license->getId());

        $this->postJson(self::URL, $payload);
        $this->assertSame('processed', $this->assertJsonResponse(200)['status']);
        $this->assertEmailCount(1);

        $this->postJson(self::URL, $payload);
        $this->assertSame('already_processed', $this->assertJsonResponse(200)['status']);
        // Le replay n'envoie pas de second e-mail...
        $this->assertEmailCount(0);

        // ...et ne crée pas de second paiement autorisé.
        $this->assertSame(1, $this->em()->getRepository(Payment::class)->count(['state' => PaymentState::AUTHORIZED]));
    }

    public function testWebhookIgnoresNonPaymentEvent(): void
    {
        $license = $this->makeEnPaiement('wh-order');

        $this->postJson(self::URL, ['eventType' => 'Order', 'data' => ['id' => 1], 'metadata' => ['licenseId' => $license->getId()]]);

        $this->assertSame('ignored', $this->assertJsonResponse(200)['status']);
        $this->em()->clear();
        $this->assertSame(LicenseStatus::EN_PAIEMENT, $this->em()->getRepository(License::class)->find($license->getId())->getStatus());
    }

    public function testWebhookIgnoresMissingLicenseId(): void
    {
        $this->postJson(self::URL, ['eventType' => 'Payment', 'data' => ['id' => 1], 'metadata' => ['memberId' => 1]]);
        $this->assertSame('ignored', $this->assertJsonResponse(200)['status']);
    }

    public function testWebhookIgnoresUnknownLicense(): void
    {
        $this->postJson(self::URL, $this->paymentPayload(999999));
        $this->assertSame('ignored', $this->assertJsonResponse(200)['status']);
    }

    public function testWebhookIgnoresMissingPaymentId(): void
    {
        $license = $this->makeEnPaiement('wh-noid');

        $this->postJson(self::URL, ['eventType' => 'Payment', 'data' => ['amount' => 12000], 'metadata' => ['licenseId' => $license->getId()]]);

        $this->assertSame('ignored', $this->assertJsonResponse(200)['status']);
    }

    public function testWebhookIgnoresLicenseWithoutPendingPayment(): void
    {
        $license = $this->aLicense()->withStatus(LicenseStatus::VALIDEE)->withAmount(12000)->persist();

        $this->postJson(self::URL, $this->paymentPayload($license->getId()));

        $this->assertSame('ignored', $this->assertJsonResponse(200)['status']);
    }

    public function testWebhookMarksPaymentRefused(): void
    {
        $license = $this->makeEnPaiement('wh-refused');
        $this->fake()->checkoutIntentResult['order']['payments'][0]['state'] = 'Refused';

        $this->postJson(self::URL, $this->paymentPayload($license->getId()));

        $this->assertSame('refused', $this->assertJsonResponse(200)['status']);
        $this->em()->clear();
        $reloaded = $this->em()->getRepository(License::class)->find($license->getId());
        $this->assertSame(LicenseStatus::VALIDEE, $reloaded->getStatus());
        $this->assertFalse($reloaded->getMember()->isLicensePaid());
    }

    public function testWebhookIgnoresPendingState(): void
    {
        $license = $this->makeEnPaiement('wh-waiting');
        $this->fake()->checkoutIntentResult['order']['payments'][0]['state'] = 'Waiting';

        $this->postJson(self::URL, $this->paymentPayload($license->getId()));

        $this->assertSame('ignored', $this->assertJsonResponse(200)['status']);
    }

    private function fake(): FakeHelloAssoClient
    {
        return static::getContainer()->get(FakeHelloAssoClient::class);
    }
}
