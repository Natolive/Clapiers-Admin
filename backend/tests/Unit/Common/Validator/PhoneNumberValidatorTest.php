<?php

namespace App\Tests\Unit\Common\Validator;

use App\Common\Validator\Constraints\PhoneNumber;
use App\Common\Validator\Constraints\PhoneNumberValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class PhoneNumberValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): PhoneNumberValidator
    {
        return new PhoneNumberValidator();
    }

    public function testValidInternationalNumberPasses(): void
    {
        $this->validator->validate('+33612345678', new PhoneNumber());

        $this->assertNoViolation();
    }

    /** Format national français, celui que le formulaire d'inscription envoie. */
    public function testValidFrenchNationalNumberPasses(): void
    {
        $this->validator->validate('0769987177', new PhoneNumber());

        $this->assertNoViolation();
    }

    /** La région par défaut ne doit pas écraser un indicatif explicite. */
    public function testForeignInternationalNumberPasses(): void
    {
        $this->validator->validate('+3227920000', new PhoneNumber());

        $this->assertNoViolation();
    }

    public function testNullAndEmptyAreValid(): void
    {
        $this->validator->validate(null, new PhoneNumber());
        $this->validator->validate('', new PhoneNumber());

        $this->assertNoViolation();
    }

    public function testInvalidNumberIsRejected(): void
    {
        // Parses (+33 prefix) but is not a valid French number
        $this->validator->validate('+3312', new PhoneNumber());

        $this->buildViolation('Le numéro de téléphone "{{ value }}" n\'est pas valide.')
            ->setParameter('{{ value }}', '+3312')
            ->assertRaised();
    }

    public function testUnparseableValueIsRejected(): void
    {
        // Aucun chiffre exploitable : le parsing lui-même échoue
        // (NumberParseException), même avec une région par défaut.
        $this->validator->validate('pas un numéro', new PhoneNumber());

        $this->buildViolation('Le numéro de téléphone "{{ value }}" n\'est pas valide.')
            ->setParameter('{{ value }}', 'pas un numéro')
            ->assertRaised();
    }

    public function testTooShortNumberIsRejected(): void
    {
        // S'analyse comme français, mais n'est pas un numéro valide
        $this->validator->validate('12', new PhoneNumber());

        $this->buildViolation('Le numéro de téléphone "{{ value }}" n\'est pas valide.')
            ->setParameter('{{ value }}', '12')
            ->assertRaised();
    }

    public function testCustomMessageIsUsed(): void
    {
        $this->validator->validate('12', new PhoneNumber(message: 'Numéro invalide'));

        $this->buildViolation('Numéro invalide')
            ->setParameter('{{ value }}', '12')
            ->assertRaised();
    }

    public function testNonStringValueIsRefused(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(123, new PhoneNumber());
    }

    public function testWrongConstraintTypeIsRefused(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('+33612345678', new NotBlank());
    }
}
