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

    /** Durée de validité d'un lien d'invitation. */
    public const INVITATION_VALIDITY = 'P7D';

    /** Durée de validité d'un lien de réinitialisation de mot de passe. */
    public const PASSWORD_RESET_VALIDITY = 'PT1H';

    /** Délai minimal entre deux demandes de réinitialisation (anti-spam de la boîte mail). */
    public const PASSWORD_RESET_COOLDOWN = 'PT5M';

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

    /**
     * Jeton du lien d'invitation : le compte invité choisit son mot de passe
     * par ce lien, qui est consommé (remis à null) à l'usage.
     */
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $invitationToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $invitationExpiresAt = null;

    /**
     * Dernier changement de mot de passe : tout JWT émis avant est refusé
     * (JwtRevocationListener), ce qui déconnecte les sessions déjà ouvertes.
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $passwordChangedAt = null;

    /** Jeton du lien « mot de passe oublié », consommé à l'usage. */
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $passwordResetToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $passwordResetExpiresAt = null;

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
     * Changement de mot de passe voulu par l'utilisateur ou un admin : révoque
     * les sessions ouvertes. Le rehash transparent au login (upgradePassword)
     * passe par setPassword et ne déconnecte personne.
     */
    public function changePassword(string $hashedPassword): static
    {
        $this->password = $hashedPassword;
        $this->passwordChangedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getPasswordChangedAt(): ?\DateTimeImmutable
    {
        return $this->passwordChangedAt;
    }

    public function setPasswordChangedAt(?\DateTimeImmutable $passwordChangedAt): static
    {
        $this->passwordChangedAt = $passwordChangedAt;

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

    /**
     * Émet un nouveau lien d'invitation ; l'ancien cesse aussitôt de fonctionner,
     * de même qu'un éventuel lien de réinitialisation : l'invitation prime.
     */
    public function renewInvitation(): static
    {
        $this->invitationToken = bin2hex(random_bytes(32));
        $this->invitationExpiresAt = (new \DateTimeImmutable('now'))->add(new \DateInterval(self::INVITATION_VALIDITY));
        $this->clearPasswordReset();

        return $this;
    }

    public function clearInvitation(): static
    {
        $this->invitationToken = null;
        $this->invitationExpiresAt = null;

        return $this;
    }

    public function getInvitationToken(): ?string
    {
        return $this->invitationToken;
    }

    public function isInvitationExpired(): bool
    {
        return $this->invitationExpiresAt !== null && $this->invitationExpiresAt < new \DateTimeImmutable('now');
    }

    /**
     * État du compte chef d'équipe : `invited` (lien envoyé, pas encore
     * utilisé), `expired` (lien périmé, à renvoyer) ou `active`.
     */
    public function getInvitationStatus(): string
    {
        return match (true) {
            !$this->isInvitationPending() => 'active',
            $this->isInvitationExpired() => 'expired',
            default => 'invited',
        };
    }

    public function isInvitationPending(): bool
    {
        return $this->invitationToken !== null;
    }

    public function renewPasswordReset(): static
    {
        $this->passwordResetToken = bin2hex(random_bytes(32));
        $this->passwordResetExpiresAt = (new \DateTimeImmutable('now'))->add(new \DateInterval(self::PASSWORD_RESET_VALIDITY));

        return $this;
    }

    public function clearPasswordReset(): static
    {
        $this->passwordResetToken = null;
        $this->passwordResetExpiresAt = null;

        return $this;
    }

    public function getPasswordResetToken(): ?string
    {
        return $this->passwordResetToken;
    }

    public function isPasswordResetExpired(): bool
    {
        return $this->passwordResetExpiresAt !== null && $this->passwordResetExpiresAt < new \DateTimeImmutable('now');
    }

    /** Un lien a-t-il été émis il y a moins de PASSWORD_RESET_COOLDOWN ? */
    public function isPasswordResetCoolingDown(): bool
    {
        if ($this->passwordResetExpiresAt === null) {
            return false;
        }
        $issuedAt = $this->passwordResetExpiresAt->sub(new \DateInterval(self::PASSWORD_RESET_VALIDITY));

        return $issuedAt->add(new \DateInterval(self::PASSWORD_RESET_COOLDOWN)) > new \DateTimeImmutable('now');
    }

    public function setPasswordResetExpiresAt(?\DateTimeImmutable $passwordResetExpiresAt): static
    {
        $this->passwordResetExpiresAt = $passwordResetExpiresAt;

        return $this;
    }

    public function setInvitationExpiresAt(?\DateTimeImmutable $invitationExpiresAt): static
    {
        $this->invitationExpiresAt = $invitationExpiresAt;

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
            'invitationStatus' => $this->getInvitationStatus(),
            'createdAt' => $this->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $this->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }
}
