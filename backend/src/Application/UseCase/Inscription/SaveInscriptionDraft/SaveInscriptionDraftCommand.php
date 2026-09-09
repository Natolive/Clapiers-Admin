<?php

namespace App\Application\UseCase\Inscription\SaveInscriptionDraft;

use App\Common\Command\CommandInterface;

class SaveInscriptionDraftCommand implements CommandInterface
{
    /**
     * @param array<string, mixed>|null $payload champs du formulaire tels que
     *                                           saisis ; `null` quand le corps
     *                                           reçu est illisible — à ne pas
     *                                           confondre avec `[]`, qui vide
     *                                           volontairement le brouillon
     */
    public function __construct(
        public readonly string $token,
        public readonly ?array $payload,
    ) {
    }
}
