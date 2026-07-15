<?php

namespace App\Common\Service\HelloAsso;

interface HelloAssoClientInterface
{
    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public function createCheckoutIntent(array $body): array;

    /**
     * @return array<string, mixed>
     */
    public function getCheckoutIntent(int $checkoutIntentId): array;

    /**
     * @return array<string, mixed>
     */
    public function getFormTiers(): array;
}
