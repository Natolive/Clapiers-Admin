<?php

namespace App\Application\UseCase\Inscription\DeleteInscriptionDraft;

use App\Common\Command\CommandInterface;

class DeleteInscriptionDraftCommand implements CommandInterface
{
    public function __construct(
        public readonly string $token,
    ) {
    }
}
