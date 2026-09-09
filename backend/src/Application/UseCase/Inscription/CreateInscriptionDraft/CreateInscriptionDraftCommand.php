<?php

namespace App\Application\UseCase\Inscription\CreateInscriptionDraft;

use App\Common\Command\CommandInterface;
use Symfony\Component\Validator\Constraints as Assert;

class CreateInscriptionDraftCommand implements CommandInterface
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $recaptchaToken,
    ) {
    }
}
