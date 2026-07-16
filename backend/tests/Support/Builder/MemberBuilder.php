<?php

namespace App\Tests\Support\Builder;

use App\Entity\Enum\LicenseStatus;
use App\Entity\Enum\MemberGender;
use App\Entity\Enum\MemberStatus;
use App\Entity\License;
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
    private ?string $licenseNumber = null;
    private ?string $licensedSeason = null;
    private LicenseStatus $licenseStatus = LicenseStatus::VALIDEE;
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

    public function withLicenseNumber(?string $number): self
    {
        $this->licenseNumber = $number;

        return $this;
    }

    /**
     * Rend le membre visible dans la liste paginée : lui attache une licence de
     * la saison donnée (validée par défaut) et le laisse ACTIVE — la population
     * de findPaginated est scopée à la saison courante.
     */
    public function licensedFor(string $season, LicenseStatus $status = LicenseStatus::VALIDEE): self
    {
        $this->licensedSeason = $season;
        $this->licenseStatus = $status;

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
        $member->setLicenseNumber($this->licenseNumber);
        $member->setTeams($this->teams);

        return $member;
    }

    public function persist(): Member
    {
        $member = $this->build();
        $this->em->persist($member);

        if ($this->licensedSeason !== null) {
            $member->setStatus(MemberStatus::ACTIVE);
            $license = new License();
            $license->setMember($member);
            $license->setSeason($this->licensedSeason);
            $license->setStatus($this->licenseStatus);
            $license->setAccessToken(sprintf('token-%s', bin2hex(random_bytes(6))));
            $this->em->persist($license);
        }

        $this->em->flush();

        return $member;
    }
}
