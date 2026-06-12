<?php

namespace App\Tests\Support\Builder;

use App\Entity\AppUser;
use App\Entity\Enum\AppUserRole;
use App\Entity\Member;
use App\Entity\Team;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppUserBuilder
{
    /** Plain-text password used by default for every test user. */
    public const DEFAULT_PASSWORD = 'password';

    private static int $seq = 0;

    private ?string $email = null;
    private string $plainPassword = self::DEFAULT_PASSWORD;
    /** @var list<string> */
    private array $roles = [];
    /** @var list<Team> */
    private array $teams = [];
    private ?Member $member = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function withEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function withPassword(string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    public function withRole(string $role): self
    {
        $this->roles = [$role];

        return $this;
    }

    public function superAdmin(): self
    {
        return $this->withRole(AppUserRole::ROLE_SUPER_ADMIN);
    }

    public function admin(): self
    {
        return $this->withRole(AppUserRole::ROLE_ADMIN);
    }

    /** Teams managed by this user (coach). */
    public function managing(Team ...$teams): self
    {
        $this->teams = array_values($teams);

        return $this;
    }

    public function linkedTo(Member $member): self
    {
        $this->member = $member;

        return $this;
    }

    public function build(): AppUser
    {
        $user = new AppUser();
        $user->setEmail($this->email ?? sprintf('user%03d@test.fr', ++self::$seq));
        $user->setRoles($this->roles);
        $user->setPassword($this->passwordHasher->hashPassword($user, $this->plainPassword));
        $user->setTeams($this->teams);
        $user->setMember($this->member);

        return $user;
    }

    public function persist(): AppUser
    {
        $user = $this->build();
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
