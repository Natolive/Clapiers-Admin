<?php

namespace App\Controller\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** Corps de POST /api/public/invitation/{token} ; le jeton vient de l'URL. */
class AcceptInvitationInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 8, minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.')]
        public readonly string $password = '',
    ) {
    }
}
