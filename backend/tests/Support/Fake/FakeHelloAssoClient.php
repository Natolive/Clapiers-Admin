<?php

namespace App\Tests\Support\Fake;

use App\Common\Service\HelloAsso\HelloAssoClientInterface;

/**
 * Implémentation de test du client HelloAsso : aucune requête réseau.
 * Les réponses sont configurables, et les appels sont enregistrés pour assertion.
 * Récupérable depuis le conteneur en test :
 *   static::getContainer()->get(FakeHelloAssoClient::class)
 */
class FakeHelloAssoClient implements HelloAssoClientInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $createdCheckoutBodies = [];

    /** @var array<string, mixed> */
    public array $checkoutIntentResponse = [
        'id' => 999001,
        'redirectUrl' => 'https://www.helloasso-sandbox.com/checkout/redirect',
    ];

    /** @var array<string, mixed> */
    public array $checkoutIntentResult = [
        'id' => 999001,
        'order' => ['id' => 888001],
        'payment' => [
            'id' => 777001,
            'amount' => 12000,
            'state' => 'Authorized',
            'paymentReceiptUrl' => 'https://www.helloasso-sandbox.com/receipt/777001',
        ],
        'metadata' => [],
    ];

    /** @var array<string, mixed> */
    public array $tiers = [
        'data' => [
            ['id' => 101, 'label' => 'Licence Loisir', 'amount' => 8000],
            ['id' => 102, 'label' => 'Licence Compétition', 'amount' => 12000],
        ],
    ];

    public function createCheckoutIntent(array $body): array
    {
        $this->createdCheckoutBodies[] = $body;

        return $this->checkoutIntentResponse;
    }

    public function getCheckoutIntent(int $checkoutIntentId): array
    {
        return $this->checkoutIntentResult;
    }

    public function getFormTiers(): array
    {
        return $this->tiers;
    }
}
