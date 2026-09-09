<?php

namespace App\Application\UseCase\Inscription\CreateInscriptionDraft;

use App\Common\Command\CommandInterface;

class CreateInscriptionDraftCommand implements CommandInterface
{
    public function __construct(
        /**
         * Pas de `NotBlank` : c'est `RecaptchaVerifier` qui tranche, et il
         * accepte tout quand `RECAPTCHA_SECRET_KEY` est vide (bypass dev
         * documenté). Une contrainte ici rendrait le formulaire entièrement
         * inutilisable dans ce mode, dès la 1re étape.
         */
        public readonly ?string $recaptchaToken = null,
    ) {
    }
}
