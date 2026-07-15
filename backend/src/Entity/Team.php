<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampableTrait;
use App\Repository\TeamRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Team
{
    use IdTrait;
    use TimestampableTrait;

    #[ORM\Column(length: 255)]
    private string $name;

    /**
     * Coachs (utilisateurs) gérant cette équipe. Côté inverse : la relation est
     * portée par AppUser.teams (table app_user_team) ; on l'édite via le user.
     *
     * @var Collection<int, AppUser>
     */
    #[ORM\ManyToMany(targetEntity: AppUser::class, mappedBy: 'teams')]
    private Collection $coaches;

    public function __construct()
    {
        $this->coaches = new ArrayCollection();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, AppUser>
     */
    public function getCoaches(): Collection
    {
        return $this->coaches;
    }

    /**
     * Maintien de cohérence en mémoire (côté inverse). La persistance passe
     * toujours par AppUser.teams (côté propriétaire).
     *
     * @internal appelé par AppUser::addTeam / removeTeam
     */
    public function addCoach(AppUser $user): void
    {
        if (!$this->coaches->contains($user)) {
            $this->coaches->add($user);
        }
    }

    /** @internal appelé par AppUser::removeTeam */
    public function removeCoach(AppUser $user): void
    {
        $this->coaches->removeElement($user);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            // Résumé léger des coachs (évite le cycle AppUser::toArray()).
            'coaches' => array_map(
                fn (AppUser $u) => ['id' => $u->getId(), 'email' => $u->getEmail()],
                $this->getCoaches()->toArray(),
            ),
            'createdAt' => $this->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $this->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }
}
