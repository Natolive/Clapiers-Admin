<?php

namespace App\Application\UseCase\User\RequestPasswordReset;

use App\Common\Command\CommandInterface;
use Symfony\Component\Validator\Constraints as Assert;

class RequestPasswordResetCommand implements CommandInterface
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public readonly string $email,
        // Vide accepté : le captcha est désactivé quand la clé n'est pas configurée.
        public readonly string $recaptchaToken = '',
    ) {
    }
}
