<?php

namespace App\Application\UseCase\Inscription\GetInscriptionDraft;

use App\Common\Command\CommandInterface;

class GetInscriptionDraftCommand implements CommandInterface
{
    public function __construct(
        public readonly string $token,
    ) {
    }
}
