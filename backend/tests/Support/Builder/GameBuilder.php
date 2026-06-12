<?php

namespace App\Tests\Support\Builder;

use App\Entity\Enum\GameVenue;
use App\Entity\Game;
use App\Entity\Team;
use Doctrine\ORM\EntityManagerInterface;

final class GameBuilder
{
    private static int $seq = 0;

    private ?string $opponent = null;
    private \DateTimeImmutable $date;
    private GameVenue $venue = GameVenue::HOME;
    private ?string $meetingTime = null;
    private ?string $location = null;
    private ?Team $team = null;

    public function __construct(private readonly EntityManagerInterface $em)
    {
        $this->date = new \DateTimeImmutable('tomorrow');
    }

    public function against(string $opponent): self
    {
        $this->opponent = $opponent;

        return $this;
    }

    public function forTeam(Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    public function onDate(string|\DateTimeImmutable $date): self
    {
        $this->date = is_string($date) ? new \DateTimeImmutable($date) : $date;

        return $this;
    }

    public function home(): self
    {
        $this->venue = GameVenue::HOME;

        return $this;
    }

    public function away(): self
    {
        $this->venue = GameVenue::AWAY;

        return $this;
    }

    public function withMeetingTime(?string $time): self
    {
        $this->meetingTime = $time;

        return $this;
    }

    public function at(?string $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function build(): Game
    {
        $game = new Game();
        $game->setOpponent($this->opponent ?? sprintf('Adversaire %03d', ++self::$seq));
        $game->setDate($this->date);
        $game->setVenue($this->venue);
        $game->setMeetingTime($this->meetingTime);
        $game->setLocation($this->location);
        $game->setTeam($this->team ?? (new TeamBuilder($this->em))->persist());

        return $game;
    }

    public function persist(): Game
    {
        $game = $this->build();
        $this->em->persist($game);
        $this->em->flush();

        return $game;
    }
}
