<?php

namespace App\Application\UseCase\License\HandleHelloAssoWebhook;

use App\Common\Command\CommandInterface;

class HandleHelloAssoWebhookCommand implements CommandInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly array $payload,
    ) {
    }
}
