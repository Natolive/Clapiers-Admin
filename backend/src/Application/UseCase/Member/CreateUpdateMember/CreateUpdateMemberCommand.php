<?php

namespace App\Application\UseCase\Member\CreateUpdateMember;

use App\Common\Command\CommandInterface;
use App\Common\Validator\Constraints\PhoneNumber;
use App\Entity\Enum\MemberGender;
use App\Entity\Enum\MemberNationality;
use Symfony\Component\Validator\Constraints as Assert;

class CreateUpdateMemberCommand implements CommandInterface
{
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,

        #[PhoneNumber]
        public readonly string $phoneNumber,

        #[Assert\Email]
        public readonly string $email,
        /** @var list<int> Équipes du licencié (au moins une) */
        #[Assert\Count(min: 1, minMessage: 'Au moins une équipe est requise')]
        #[Assert\All([new Assert\Type('integer')])]
        public readonly array $teamIds,
        #[Assert\NotBlank] #[Assert\Length(max: 255)]
        public readonly string $addressStreet,
        #[Assert\NotBlank] #[Assert\Length(max: 10)]
        public readonly string $addressZip,
        #[Assert\NotBlank] #[Assert\Length(max: 100)]
        public readonly string $addressCity,
        public readonly MemberGender $gender,
        public readonly string $birthDate,

        #[Assert\NotBlank]
        #[Assert\Choice(callback: [MemberNationality::class, 'values'])]
        public readonly string $nationality,
        #[Assert\Length(max: 50)]
        public readonly ?string $licenseNumber = null,
        public readonly ?int $id = null,
    ) {
    }
}
