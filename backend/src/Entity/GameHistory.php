<?php

namespace App\Entity;

use App\Entity\Enum\GameHistoryAction;
use App\Entity\Trait\IdTrait;
use App\Repository\GameHistoryRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Append-only audit row for a transaction on a {@see Game} (create/update/delete).
 *
 * Deliberately decoupled from Game/AppUser: there are no ORM associations, only
 * denormalized scalars (gameId, opponent, date, team, actorEmail). The log
 * therefore survives deletion of the game or the actor, and never holds a stale
 * reference to a removed entity.
 */
#[ORM\Entity(repositoryClass: GameHistoryRepository::class)]
#[ORM\Index(name: 'idx_game_history_game_id', columns: ['game_id'])]
#[ORM\Index(name: 'idx_game_history_team_id', columns: ['team_id'])]
class GameHistory
{
    use IdTrait;

    #[ORM\Column(type: 'string', enumType: GameHistoryAction::class, length: 10)]
    private GameHistoryAction $action;

    /** Durable game identifier — survives deletion of the game. */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $gameId = null;

    #[ORM\Column(length: 255)]
    private string $opponent;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $gameDate;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $teamId = null;

    #[ORM\Column(length: 255)]
    private string $teamName;

    /**
     * Field-level diff. CREATED/DELETED store a full snapshot ({field: value});
     * UPDATED stores changed fields only ({field: {old, new}}).
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $changes = [];

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $actorEmail = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable('now');
    }

    public function getAction(): GameHistoryAction
    {
        return $this->action;
    }

    public function setAction(GameHistoryAction $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getGameId(): ?int
    {
        return $this->gameId;
    }

    public function setGameId(?int $gameId): static
    {
        $this->gameId = $gameId;
        return $this;
    }

    public function getOpponent(): string
    {
        return $this->opponent;
    }

    public function setOpponent(string $opponent): static
    {
        $this->opponent = $opponent;
        return $this;
    }

    public function getGameDate(): DateTimeImmutable
    {
        return $this->gameDate;
    }

    public function setGameDate(DateTimeImmutable $gameDate): static
    {
        $this->gameDate = $gameDate;
        return $this;
    }

    public function getTeamId(): ?int
    {
        return $this->teamId;
    }

    public function setTeamId(?int $teamId): static
    {
        $this->teamId = $teamId;
        return $this;
    }

    public function getTeamName(): string
    {
        return $this->teamName;
    }

    public function setTeamName(string $teamName): static
    {
        $this->teamName = $teamName;
        return $this;
    }

    /** @return array<string, mixed> */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /** @param array<string, mixed> $changes */
    public function setChanges(array $changes): static
    {
        $this->changes = $changes;
        return $this;
    }

    public function getActorEmail(): ?string
    {
        return $this->actorEmail;
    }

    public function setActorEmail(?string $actorEmail): static
    {
        $this->actorEmail = $actorEmail;
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->getId(),
            'action'     => $this->getAction()->value,
            'gameId'     => $this->getGameId(),
            'opponent'   => $this->getOpponent(),
            'gameDate'   => $this->getGameDate()->format('Y-m-d'),
            'teamId'     => $this->getTeamId(),
            'teamName'   => $this->getTeamName(),
            'changes'    => $this->getChanges(),
            'actorEmail' => $this->getActorEmail(),
            'createdAt'  => $this->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
