<?php

namespace App\Application\UseCase\License\SubmitLicenseRequest;

use App\Common\Command\CommandInterface;
use App\Common\Validator\Constraints\PhoneNumber;
use App\Entity\Enum\MemberGender;
use App\Entity\Enum\MemberNationality;
use Symfony\Component\Validator\Constraints as Assert;

class SubmitLicenseRequestCommand implements CommandInterface
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $firstName,

        #[Assert\NotBlank]
        public readonly string $lastName,

        #[PhoneNumber]
        public readonly string $phoneNumber,

        #[Assert\NotBlank]
        #[Assert\Email]
        public readonly string $email,

        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $addressStreet,

        #[Assert\NotBlank]
        #[Assert\Length(max: 10)]
        public readonly string $addressZip,

        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public readonly string $addressCity,

        public readonly MemberGender $gender,

        #[Assert\NotBlank]
        public readonly string $birthDate,

        #[Assert\NotBlank]
        #[Assert\Choice(callback: [MemberNationality::class, 'values'])]
        public readonly string $nationality,

        #[Assert\NotBlank]
        public readonly string $recaptchaToken,

        #[Assert\Length(max: 50)]
        public readonly ?string $licenseNumber = null,
    ) {
    }
}
