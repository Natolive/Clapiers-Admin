<?php

namespace App\Entity;

use App\Entity\Enum\AppUserRole;
use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampableTrait;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
// Suppression douce, en cascade depuis le licencié lié. Le provider de sécurité
// est un provider `entity` : il passe par l'ORM, donc le filtre `softdeleteable`
// s'y applique et un compte supprimé ne peut plus s'authentifier — ni par mot de
// passe, ni avec un JWT déjà émis, l'utilisateur étant rechargé à chaque requête.
//
// L'email reste occupé par la ligne masquée (contrainte d'unicité en base) :
// recréer un compte avec la même adresse imposera de restaurer l'ancien.
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt')]
class AppUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    use IdTrait;
    use SoftDeleteableEntity;
    use TimestampableTrait;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\OneToOne(targetEntity: Member::class)]
    #[ORM\JoinColumn(nullable: true, unique: true)]
    private ?Member $member = null;

    /**
     * Équipes gérées par l'utilisateur (coachs) — indépendant du licencié lié.
     *
     * @var Collection<int, Team>
     */
    #[ORM\ManyToMany(targetEntity: Team::class)]
    #[ORM\JoinTable(name: 'app_user_team')]
    private Collection $teams;

    public function __construct()
    {
        $this->teams = new ArrayCollection();
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = AppUserRole::USER->value;

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function getMember(): ?Member
    {
        return $this->member;
    }

    public function setMember(?Member $member): static
    {
        $this->member = $member;

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
        // Comparaison d'identité d'abord : deux équipes non persistées ont
        // toutes deux un id null et seraient sinon considérées égales
        return $this->teams->exists(
            fn (int $key, Team $t) => $t === $team || ($t->getId() !== null && $t->getId() === $team->getId())
        );
    }

    public function addTeam(Team $team): static
    {
        if (!$this->hasTeam($team)) {
            $this->teams->add($team);
            $team->addCoach($this); // cohérence en mémoire (côté inverse)
        }

        return $this;
    }

    public function removeTeam(Team $team): static
    {
        foreach ($this->teams as $t) {
            if ($t === $team || ($t->getId() !== null && $t->getId() === $team->getId())) {
                $this->teams->removeElement($t);
                $team->removeCoach($this);
                break;
            }
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

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'email' => $this->getEmail(),
            'roles' => $this->getRoles(),
            'member' => $this->getMember()?->toArray(),
            'teams' => array_map(fn (Team $t) => $t->toArray(), $this->getTeams()->toArray()),
            'createdAt' => $this->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $this->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }
}
