<?php

namespace App\Tests\Support\Builder;

use App\Entity\Enum\MemberGender;
use App\Entity\Member;
use App\Entity\Team;
use App\Entity\ValueObject\Address;
use Doctrine\ORM\EntityManagerInterface;

final class MemberBuilder
{
    private static int $seq = 0;

    private ?string $firstName = null;
    private ?string $lastName = null;
    private ?string $email = null;
    private string $phoneNumber = '+33612345678';
    private MemberGender $gender = MemberGender::MALE;
    private \DateTimeImmutable $birthDate;
    private string $nationality = 'Française';
    private Address $address;
    private bool $licensePaid = false;
    private ?string $licenseNumber = null;
    private ?string $licenseFileName = null;
    private ?string $profilePicture = null;
    /** @var list<Team> */
    private array $teams = [];

    public function __construct(private readonly EntityManagerInterface $em)
    {
        $this->birthDate = new \DateTimeImmutable('1990-01-15');
        $this->address = new Address('1 rue du Stade', '34830', 'Clapiers');
    }

    public function named(string $firstName, string $lastName): self
    {
        $this->firstName = $firstName;
        $this->lastName = $lastName;

        return $this;
    }

    public function withEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function withGender(MemberGender $gender): self
    {
        $this->gender = $gender;

        return $this;
    }

    public function inTeams(Team ...$teams): self
    {
        $this->teams = array_values($teams);

        return $this;
    }

    public function licensePaid(bool $paid = true): self
    {
        $this->licensePaid = $paid;

        return $this;
    }

    public function withLicenseNumber(?string $number): self
    {
        $this->licenseNumber = $number;

        return $this;
    }

    /** Only sets the DB column — write the file yourself if the test reads it. */
    public function withLicenseFileName(?string $fileName): self
    {
        $this->licenseFileName = $fileName;

        return $this;
    }

    public function withProfilePicture(?string $fileName): self
    {
        $this->profilePicture = $fileName;

        return $this;
    }

    public function build(): Member
    {
        $n = ++self::$seq;

        $member = new Member();
        $member->setFirstName($this->firstName ?? sprintf('Prénom%03d', $n));
        $member->setLastName($this->lastName ?? sprintf('Nom%03d', $n));
        $member->setEmail($this->email ?? sprintf('membre%03d@test.fr', $n));
        $member->setPhoneNumber($this->phoneNumber);
        $member->setGender($this->gender);
        $member->setBirthDate($this->birthDate);
        $member->setNationality($this->nationality);
        $member->setAddress($this->address);
        $member->setLicensePaid($this->licensePaid);
        $member->setLicenseNumber($this->licenseNumber);
        $member->setLicenseFileName($this->licenseFileName);
        $member->setProfilePicture($this->profilePicture);
        $member->setTeams($this->teams);

        return $member;
    }

    public function persist(): Member
    {
        $member = $this->build();
        $this->em->persist($member);
        $this->em->flush();

        return $member;
    }
}
