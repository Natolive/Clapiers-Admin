<?php

namespace App\Application\UseCase\Inscription\DeleteInscriptionDraftDocument;

use App\Common\Command\CommandInterface;

class DeleteInscriptionDraftDocumentCommand implements CommandInterface
{
    public function __construct(
        public readonly string $token,
        public readonly string $systemKey,
    ) {
    }
}
