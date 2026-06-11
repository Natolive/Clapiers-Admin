<?php

namespace App\Entity;

use App\Entity\Enum\MemberGender;
use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampableTrait;
use App\Entity\ValueObject\Address;
use App\Repository\MemberRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    /**
     * @var Collection<int, Team>
     */
    #[ORM\ManyToMany(targetEntity: Team::class)]
    #[ORM\JoinTable(name: 'member_team')]
    private Collection $teams;

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
        $this->teams   = new ArrayCollection();
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

    /**
     * @return Collection<int, Team>
     */
    public function getTeams(): Collection
    {
        return $this->teams;
    }

    public function hasTeam(Team $team): bool
    {
        return $this->teams->exists(fn (int $key, Team $t) => $t->getId() === $team->getId());
    }

    public function addTeam(Team $team): static
    {
        if (!$this->hasTeam($team)) {
            $this->teams->add($team);
        }

        return $this;
    }

    /**
     * @param iterable<Team> $teams
     */
    public function setTeams(iterable $teams): static
    {
        $this->teams->clear();
        foreach ($teams as $team) {
            $this->addTeam($team);
        }

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
            'teams' => array_map(fn (Team $t) => $t->toArray(), $this->getTeams()->toArray()),
            'createdAt' => $this->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $this->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }
}
