<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampableTrait;
use App\Repository\SeasonRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Saison sportive enregistrée (ex. « 2026-2027 »). Alimente le sélecteur de
 * saison. Une saison est ajoutée ici dès qu'elle est retenue comme courante.
 */
#[ORM\Entity(repositoryClass: SeasonRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Season
{
    use IdTrait;
    use TimestampableTrait;

    #[ORM\Column(length: 9, unique: true)]
    private string $name;

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }
}
