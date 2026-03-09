<?php

namespace App\Entity;

use App\Entity\Enum\GameVenue;
use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampableTrait;
use App\Repository\GameRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Game
{
    use IdTrait;
    use TimestampableTrait;

    #[ORM\Column(length: 255)]
    private string $opponent;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $date;

    /** Informative only — e.g. "14h30" */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $meetingTime = null;

    #[ORM\Column(type: 'string', enumType: GameVenue::class, length: 10)]
    private GameVenue $venue = GameVenue::HOME;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Team $team;

    public function getOpponent(): string
    {
        return $this->opponent;
    }

    public function setOpponent(string $opponent): static
    {
        $this->opponent = $opponent;
        return $this;
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(DateTimeImmutable $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function getMeetingTime(): ?string
    {
        return $this->meetingTime;
    }

    public function setMeetingTime(?string $meetingTime): static
    {
        $this->meetingTime = $meetingTime;
        return $this;
    }

    public function getVenue(): GameVenue
    {
        return $this->venue;
    }

    public function setVenue(GameVenue $venue): static
    {
        $this->venue = $venue;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;
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

    public function toArray(): array
    {
        return [
            'id'          => $this->getId(),
            'opponent'    => $this->getOpponent(),
            'date'        => $this->getDate()->format('Y-m-d'),
            'meetingTime' => $this->getMeetingTime(),
            'venue'       => $this->getVenue()->value,
            'location'    => $this->getLocation(),
            'team'        => $this->getTeam()->toArray(),
            'createdAt'   => $this->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt'   => $this->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }
}
