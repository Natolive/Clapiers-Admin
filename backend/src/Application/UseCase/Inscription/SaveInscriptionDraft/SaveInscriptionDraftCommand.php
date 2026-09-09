<?php

namespace App\Application\UseCase\Inscription\SaveInscriptionDraft;

use App\Common\Command\CommandInterface;

class SaveInscriptionDraftCommand implements CommandInterface
{
    /** @param array<string, mixed> $payload champs du formulaire tels que saisis */
    public function __construct(
        public readonly string $token,
        public readonly array $payload,
    ) {
    }
}
