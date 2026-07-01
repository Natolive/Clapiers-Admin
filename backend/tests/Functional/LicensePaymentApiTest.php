<?php

namespace App\Tests\Functional;

use App\Entity\Enum\LicenseStatus;
use App\Entity\Enum\PaymentState;
use App\Entity\License;
use App\Entity\Payment;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Fake\FakeHelloAssoClient;

/**
 * Portail public de paiement (magic link) : consultation + création du
 * checkout HelloAsso. Le client HelloAsso est un fake (zéro réseau).
 */
class LicensePaymentApiTest extends ApiTestCase
{
    public function testGetForPaymentReturnsPortalView(): void
    {
        $this->aLicense()
            ->withToken('tok-view')
            ->withStatus(LicenseStatus::VALIDEE)
            ->withAmount(12000)
            ->persist();

        $this->getJson('/api/public/license/tok-view');

        $body = $this->assertJsonResponse(200);
        $this->assertSame('validee', $body['status']);
        $this->assertSame(12000, $body['amount']);
        $this->assertArrayHasKey('firstName', $body);
        // La vue portail ne fuit pas l'adresse ni le token
        $this->assertArrayNotHasKey('accessToken', $body);
    }

    public function testGetForPaymentUnknownTokenReturns404(): void
    {
        $this->getJson('/api/public/license/nope');
        $this->assertJsonResponse(404);
    }

    public function testCheckoutReturnsRedirectUrlAndMarksEnPaiement(): void
    {
        $license = $this->aLicense()
            ->withToken('tok-co')
            ->withStatus(LicenseStatus::VALIDEE)
            ->withAmount(12000)
            ->persist();
        $licenseId = $license->getId();

        $this->postJson('/api/public/license/tok-co/checkout');

        $body = $this->assertJsonResponse(200);
        $this->assertSame('https://www.helloasso-sandbox.com/checkout/redirect', $body['redirectUrl']);

        $this->em()->clear();
        $reloaded = $this->em()->getRepository(License::class)->find($licenseId);
        $this->assertSame(LicenseStatus::EN_PAIEMENT, $reloaded->getStatus());

        $payment = $this->em()->getRepository(Payment::class)->findOneBy(['helloAssoCheckoutIntentId' => 999001]);
        $this->assertNotNull($payment);
        $this->assertSame(PaymentState::WAITING, $payment->getState());
        $this->assertSame(12000, $payment->getAmount());

        // Le corps envoyé à HelloAsso porte le montant et la metadata de réconciliation
        $fake = static::getContainer()->get(FakeHelloAssoClient::class);
        $this->assertCount(1, $fake->createdCheckoutBodies);
        $this->assertSame(12000, $fake->createdCheckoutBodies[0]['totalAmount']);
        $this->assertSame($licenseId, $fake->createdCheckoutBodies[0]['metadata']['licenseId']);
    }

    public function testCheckoutUnknownTokenReturns404(): void
    {
        $this->postJson('/api/public/license/nope/checkout');
        $this->assertJsonResponse(404);
    }

    public function testCheckoutNotPayableReturns409(): void
    {
        $this->aLicense()->withToken('tok-soumise')->withStatus(LicenseStatus::SOUMISE)->persist();

        $this->postJson('/api/public/license/tok-soumise/checkout');

        $this->assertJsonResponse(409);
    }

    public function testCheckoutWithoutAmountReturns409(): void
    {
        $this->aLicense()->withToken('tok-noamount')->withStatus(LicenseStatus::VALIDEE)->withAmount(null)->persist();

        $this->postJson('/api/public/license/tok-noamount/checkout');

        $this->assertJsonResponse(409);
    }
}
