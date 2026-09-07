<?php

namespace App\Common\Validator\Constraints;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class PhoneNumberValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PhoneNumber) {
            throw new UnexpectedTypeException($constraint, PhoneNumber::class);
        }

        // Null and empty strings are valid (use NotBlank constraint separately if needed)
        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $phoneUtil = PhoneNumberUtil::getInstance();

        try {
            // Région par défaut « FR » : sans elle, seuls les numéros préfixés
            // « + » sont analysables et un « 0769987177 » saisi au format
            // national lève une NumberParseException. Le front valide déjà
            // contre 'FR' (isValidPhoneNumber), les deux doivent s'accorder.
            // Un numéro international garde son propre indicatif, la région par
            // défaut n'étant consultée qu'en l'absence de « + ».
            $numberProto = $phoneUtil->parse($value, 'FR');

            if (!$phoneUtil->isValidNumber($numberProto)) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $value)
                    ->addViolation();
            }
        } catch (NumberParseException $e) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
