<?php

namespace App\Common\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class PhoneNumber extends Constraint
{
    public string $message = 'Le numéro de téléphone "{{ value }}" n\'est pas valide.';

    public function __construct(
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct([], $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
