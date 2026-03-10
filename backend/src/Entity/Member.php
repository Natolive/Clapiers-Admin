<?php

namespace App\Entity;

use App\Entity\Enum\MemberGender;
use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampableTrait;
use App\Entity\ValueObject\Address;
use App\Repository\MemberRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MemberRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Member
{
    use IdTrait;
    use TimestampableTrait;

    #[ORM\Column(length: 255)]
    private string $firstName;

    #[ORM\Column(length: 255)]
    private string $lastName;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Team $team;

    #[ORM\Column(length: 7)]
    private string $color;

    #[ORM\Column(length: 30)]
    private string $phoneNumber;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $licensePaid = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $licenseFileName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $profilePicture = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $licenseNumber = null;

    #[ORM\Embedded(class: Address::class, columnPrefix: 'address_')]
    private Address $address;

    #[ORM\Column(length: 10, enumType: MemberGender::class)]
    private MemberGender $gender;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $birthDate;

    #[ORM\Column(length: 100)]
    private string $nationality;

    public function __construct()
    {
        $this->color   = $this->generateRandomHexColor();
        $this->address = new Address();
    }

    private function generateRandomHexColor(): string
    {
        return sprintf('#%06X', mt_rand(0, 0xFFFFFF));
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getTeam(): Team
    {
        return $this->team;
    }

    public function setTeam(Team $team): static
    {
        $this->team = $team;

        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function isLicensePaid(): bool
    {
        return $this->licensePaid;
    }

    public function setLicensePaid(bool $licensePaid): static
    {
        $this->licensePaid = $licensePaid;

        return $this;
    }

    public function getLicenseFileName(): ?string
    {
        return $this->licenseFileName;
    }

    public function setLicenseFileName(?string $licenseFileName): static
    {
        $this->licenseFileName = $licenseFileName;

        return $this;
    }

    public function getProfilePicture(): ?string
    {
        return $this->profilePicture;
    }

    public function setProfilePicture(?string $profilePicture): static
    {
        $this->profilePicture = $profilePicture;

        return $this;
    }

    public function getLicenseNumber(): ?string { return $this->licenseNumber; }
    public function setLicenseNumber(?string $licenseNumber): static { $this->licenseNumber = $licenseNumber; return $this; }

    public function getAddress(): Address { return $this->address; }
    public function setAddress(Address $address): static { $this->address = $address; return $this; }

    public function getGender(): MemberGender
    {
        return $this->gender;
    }

    public function setGender(MemberGender $gender): static
    {
        $this->gender = $gender;

        return $this;
    }

    public function getBirthDate(): \DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function setBirthDate(\DateTimeImmutable $birthDate): static
    {
        $this->birthDate = $birthDate;

        return $this;
    }

    public function getNationality(): string
    {
        return $this->nationality;
    }

    public function setNationality(string $nationality): static
    {
        $this->nationality = $nationality;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'firstName' => $this->getFirstName(),
            'lastName' => $this->getLastName(),
            'color' => $this->getColor(),
            'phoneNumber' => $this->getPhoneNumber(),
            'email' => $this->getEmail(),
            'licensePaid' => $this->isLicensePaid(),
            'licenseFileName' => $this->getLicenseFileName(),
            'profilePicture' => $this->getProfilePicture(),
            'licenseNumber' => $this->getLicenseNumber(),
            'address'       => $this->getAddress()->toArray(),
            'gender' => $this->getGender()->value,
            'birthDate' => $this->getBirthDate()->format('Y-m-d'),
            'nationality' => $this->getNationality(),
            'team' => $this->getTeam()->toArray(),
            'createdAt' => $this->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $this->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }
}
