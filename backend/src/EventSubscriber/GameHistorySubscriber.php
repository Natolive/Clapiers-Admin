<?php

namespace App\EventSubscriber;

use App\Entity\AppUser;
use App\Entity\Enum\GameHistoryAction;
use App\Entity\Game;
use App\Entity\GameHistory;
use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Records every transaction (create/update/delete) on a {@see Game} into a
 * {@see GameHistory} row, using Doctrine's UnitOfWork change-sets for the
 * field-level diff.
 *
 * Inserts are collected in onFlush (where their id is not yet assigned) and the
 * rows are written in postFlush (id now available) via a guarded second flush.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class GameHistorySubscriber
{
    /** @var array<int, array<string, mixed>> */
    private array $pending = [];

    private bool $flushing = false;

    public function __construct(private readonly Security $security)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof Game) {
                // A freshly inserted game has no id yet — resolve it in postFlush.
                $this->pending[] = $this->payload(GameHistoryAction::CREATED, $entity, $this->snapshot($entity), needsId: true);
            }
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof Game) {
                $changes = $this->diff($uow->getEntityChangeSet($entity));
                if ($changes !== []) {
                    $this->pending[] = $this->payload(GameHistoryAction::UPDATED, $entity, $changes, needsId: false);
                }
            }
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof Game) {
                $this->pending[] = $this->payload(GameHistoryAction::DELETED, $entity, $this->snapshot($entity), needsId: false);
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->pending === [] || $this->flushing) {
            return;
        }

        $em     = $args->getObjectManager();
        $actor  = $this->security->getUser();
        $actor  = $actor instanceof AppUser ? $actor : null;

        $rows = $this->pending;
        $this->pending = [];

        foreach ($rows as $row) {
            /** @var Game $game */
            $game = $row['game'];

            $history = new GameHistory();
            $history->setAction($row['action']);
            $history->setGameId($row['needsId'] ? $game->getId() : $row['gameId']);
            $history->setOpponent($game->getOpponent());
            $history->setGameDate($game->getDate());
            $history->setTeamId($game->getTeam()->getId());
            $history->setTeamName($game->getTeam()->getName());
            $history->setChanges($row['changes']);
            $history->setActorEmail($actor?->getEmail());

            $em->persist($history);
        }

        $this->flushing = true;
        try {
            $em->flush();
        } finally {
            $this->flushing = false;
        }
    }

    /**
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function payload(GameHistoryAction $action, Game $game, array $changes, bool $needsId): array
    {
        return [
            'action'  => $action,
            'game'    => $game,
            'gameId'  => $game->getId(),
            'changes' => $changes,
            'needsId' => $needsId,
        ];
    }

    /**
     * Full field snapshot for create/delete.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Game $game): array
    {
        return [
            'opponent'    => $game->getOpponent(),
            'date'        => $game->getDate()->format('Y-m-d'),
            'meetingTime' => $game->getMeetingTime(),
            'venue'       => $game->getVenue()->value,
            'location'    => $game->getLocation(),
            'team'        => $game->getTeam()->getName(),
        ];
    }

    /**
     * Turns Doctrine's change-set into a serializable {field: {old, new}} diff,
     * dropping fields whose value did not actually change.
     *
     * @param array<string, array{0: mixed, 1: mixed}> $changeSet
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function diff(array $changeSet): array
    {
        $diff = [];

        foreach ($changeSet as $field => [$old, $new]) {
            $oldValue = $this->scalarize($old);
            $newValue = $this->scalarize($new);

            // Doctrine flags a field as dirty when only the object identity changed
            // (e.g. `date` is rehydrated into a fresh DateTimeImmutable on every save):
            // skip fields whose serialized value is actually unchanged.
            if ($oldValue === $newValue) {
                continue;
            }

            $diff[$field] = ['old' => $oldValue, 'new' => $newValue];
        }

        return $diff;
    }

    /**
     * Doctrine hands back enum-typed fields as their backing scalar already; only
     * dates and the Team association need flattening to a serializable value.
     */
    private function scalarize(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if ($value instanceof Team) {
            return $value->getName();
        }

        return $value;
    }
}
