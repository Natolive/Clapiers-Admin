<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Repository\LogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ligne de log applicative persistée par le DatabaseLogger, consultable depuis
 * la page d'administration (SUPER_ADMIN). Écrite en base via une insertion DBAL
 * brute (jamais via l'ORM) pour rester fiable même pendant une exception.
 */
#[ORM\Entity(repositoryClass: LogRepository::class)]
#[ORM\Table(name: 'log')]
#[ORM\Index(name: 'idx_log_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_log_level', columns: ['level'])]
class Log
{
    use IdTrait;

    #[ORM\Column(length: 20)]
    private string $level;

    #[ORM\Column(length: 50)]
    private string $channel = 'app';

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    /** Contexte brut sérialisé en JSON (exceptions, données de requête…). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $context = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function getLevel(): string
    {
        return $this->level;
    }

    public function setLevel(string $level): static
    {
        $this->level = $level;

        return $this;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function setChannel(string $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(?string $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'level' => $this->getLevel(),
            'channel' => $this->getChannel(),
            'message' => $this->getMessage(),
            'context' => $this->context !== null ? json_decode($this->context, true) : null,
            'createdAt' => $this->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
