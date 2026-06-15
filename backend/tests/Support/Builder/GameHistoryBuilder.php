<?php

namespace App\Tests\Support\Builder;

use App\Entity\Enum\GameHistoryAction;
use App\Entity\Game;
use App\Entity\GameHistory;
use Doctrine\ORM\EntityManagerInterface;

final class GameHistoryBuilder
{
    private static int $seq = 0;

    private GameHistoryAction $action = GameHistoryAction::CREATED;
    private ?int $gameId = null;
    private ?string $opponent = null;
    private \DateTimeImmutable $gameDate;
    private ?int $teamId = null;
    private ?string $teamName = null;
    /** @var array<string, mixed> */
    private array $changes = [];
    private ?string $actorEmail = null;
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct(private readonly EntityManagerInterface $em)
    {
        $this->gameDate = new \DateTimeImmutable('tomorrow');
    }

    public function action(GameHistoryAction $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function forGame(Game $game): self
    {
        $this->gameId   = $game->getId();
        $this->opponent = $game->getOpponent();
        $this->gameDate = $game->getDate();
        $this->teamId   = $game->getTeam()->getId();
        $this->teamName = $game->getTeam()->getName();

        return $this;
    }

    public function forTeamId(int $teamId): self
    {
        $this->teamId = $teamId;

        return $this;
    }

    public function forGameId(int $gameId): self
    {
        $this->gameId = $gameId;

        return $this;
    }

    /** @param array<string, mixed> $changes */
    public function withChanges(array $changes): self
    {
        $this->changes = $changes;

        return $this;
    }

    public function by(?string $actorEmail): self
    {
        $this->actorEmail = $actorEmail;

        return $this;
    }

    public function at(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function build(): GameHistory
    {
        $history = new GameHistory();
        $history->setAction($this->action);
        $history->setGameId($this->gameId);
        $history->setOpponent($this->opponent ?? sprintf('Adversaire %03d', ++self::$seq));
        $history->setGameDate($this->gameDate);
        $history->setTeamId($this->teamId);
        $history->setTeamName($this->teamName ?? 'Équipe '.self::$seq);
        $history->setChanges($this->changes);
        $history->setActorEmail($this->actorEmail);

        if ($this->createdAt !== null) {
            $history->setCreatedAt($this->createdAt);
        }

        return $history;
    }

    public function persist(): GameHistory
    {
        $history = $this->build();
        $this->em->persist($history);
        $this->em->flush();

        return $history;
    }
}
