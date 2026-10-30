<?php

namespace App\Controller\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** Corps des POST /api/public/invitation/{token} et /password-reset/{token} ; le jeton vient de l'URL. */
class SetPasswordInput
{
    public function __construct(
        #[Assert\NotBlank]
        // max : au-delà de 4096 le hasher de Symfony lève une exception (500).
        #[Assert\Length(
            min: 8,
            max: 4096,
            minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.',
            maxMessage: 'Le mot de passe ne peut pas dépasser {{ limit }} caractères.',
        )]
        public readonly string $password = '',
    ) {
    }
}
