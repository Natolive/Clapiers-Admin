<?php

namespace App\Tests\Functional;

use App\Entity\Enum\GameHistoryAction;
use App\Entity\GameHistory;
use App\Tests\Support\ApiTestCase;

/**
 * /api/game/history — journal des transactions sur les matchs (super admin only).
 *
 * Capture côté écriture : un GameHistorySubscriber Doctrine enregistre une ligne
 * pour chaque création/modification/suppression de match. Lecture : endpoint
 * paginé réservé au super admin.
 */
class GameHistoryApiTest extends ApiTestCase
{
    // ── Capture (write side, via the Game API) ───────────────────────────────

    public function testCreatingGameRecordsACreatedEntryWithSnapshotAndActor(): void
    {
        $team = $this->aTeam()->persist();

        $superAdmin = $this->actingAsSuperAdmin();
        $this->postJson('/api/game', [
            'opponent'    => 'Montpellier VB',
            'date'        => '2026-09-12',
            'meetingTime' => '14h00',
            'venue'       => 'home',
            'location'    => 'Gymnase de Clapiers',
            'teamId'      => $team->getId(),
        ]);
        $created = $this->assertJsonResponse(200);

        $this->getJson('/api/game/history');
        $body = $this->assertJsonResponse(200);

        $this->assertSame(1, $body['total']);
        $this->assertCount(1, $body['data']);

        $entry = $body['data'][0];
        $this->assertSame('created', $entry['action']);
        $this->assertSame($created['id'], $entry['gameId']);
        $this->assertSame('Montpellier VB', $entry['opponent']);
        $this->assertSame('2026-09-12', $entry['gameDate']);
        $this->assertSame($team->getId(), $entry['teamId']);
        $this->assertSame($team->getName(), $entry['teamName']);
        $this->assertSame($superAdmin->getEmail(), $entry['actorEmail']);
        $this->assertSame('home', $entry['changes']['venue']);
        $this->assertSame($team->getName(), $entry['changes']['team']);
    }

    public function testUpdatingGameRecordsFieldLevelBeforeAfterDiff(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/game', [
            'opponent' => 'Nîmes',
            'date'     => '2026-09-13',
            'venue'    => 'home',
            'teamId'   => $team->getId(),
        ]);
        $game = $this->assertJsonResponse(200);

        $this->putJson('/api/game/'.$game['id'], [
            'opponent' => 'Nîmes Volley',
            'date'     => '2026-09-13',
            'venue'    => 'away',
            'teamId'   => $team->getId(),
        ]);
        $this->assertJsonResponse(200);

        $this->getJson('/api/game/history?gameId='.$game['id']);
        $body = $this->assertJsonResponse(200);

        // Most recent first: the update is at index 0.
        $this->assertSame(2, $body['total']);
        $update = $body['data'][0];
        $this->assertSame('updated', $update['action']);
        $this->assertSame(['old' => 'Nîmes', 'new' => 'Nîmes Volley'], $update['changes']['opponent']);
        $this->assertSame(['old' => 'home', 'new' => 'away'], $update['changes']['venue']);
        // createdAt/updatedAt timestamps are never part of the diff.
        $this->assertArrayNotHasKey('updatedAt', $update['changes']);
        // The date was resubmitted unchanged: it must NOT appear as a change,
        // even though Doctrine rehydrates it into a fresh DateTimeImmutable.
        $this->assertArrayNotHasKey('date', $update['changes']);
    }

    public function testResavingAGameWithNoRealChangeRecordsNothing(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/game', [
            'opponent' => 'Béziers',
            'date'     => '2026-09-21',
            'venue'    => 'away',
            'teamId'   => $team->getId(),
        ]);
        $game = $this->assertJsonResponse(200);

        // Resubmit the exact same payload — nothing actually changes.
        $this->putJson('/api/game/'.$game['id'], [
            'opponent' => 'Béziers',
            'date'     => '2026-09-21',
            'venue'    => 'away',
            'teamId'   => $team->getId(),
        ]);
        $this->assertJsonResponse(200);

        $this->getJson('/api/game/history?gameId='.$game['id']);
        $body = $this->assertJsonResponse(200);

        // Only the original "created" entry — no spurious "updated" row.
        $this->assertSame(1, $body['total']);
        $this->assertSame('created', $body['data'][0]['action']);
    }

    public function testReassigningTeamIsRecordedAsATeamNameDiff(): void
    {
        $teamA = $this->aTeam()->persist();
        $teamB = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/game', [
            'opponent' => 'Lunel',
            'date'     => '2026-09-20',
            'venue'    => 'away',
            'teamId'   => $teamA->getId(),
        ]);
        $game = $this->assertJsonResponse(200);

        $this->putJson('/api/game/'.$game['id'], [
            'opponent' => 'Lunel',
            'date'     => '2026-09-20',
            'venue'    => 'away',
            'teamId'   => $teamB->getId(),
        ]);
        $this->assertJsonResponse(200);

        $this->getJson('/api/game/history?gameId='.$game['id']);
        $body = $this->assertJsonResponse(200);

        $update = $body['data'][0];
        $this->assertSame('updated', $update['action']);
        $this->assertSame(
            ['old' => $teamA->getName(), 'new' => $teamB->getName()],
            $update['changes']['team']
        );
    }

    public function testDeletingGameRecordsAnEntryThatSurvivesDeletion(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/game', [
            'opponent' => 'Sète',
            'date'     => '2026-09-14',
            'venue'    => 'away',
            'teamId'   => $team->getId(),
        ]);
        $game   = $this->assertJsonResponse(200);
        $gameId = $game['id'];

        $this->deleteJson('/api/game/'.$gameId);
        $this->assertJsonResponse(200);

        // The game is gone…
        $this->assertNull($this->em()->getRepository(\App\Entity\Game::class)->find($gameId));

        // …but its history (created + deleted) is still readable by gameId.
        $this->getJson('/api/game/history?gameId='.$gameId);
        $body = $this->assertJsonResponse(200);

        $this->assertSame(2, $body['total']);
        $deleted = $body['data'][0];
        $this->assertSame('deleted', $deleted['action']);
        $this->assertSame($gameId, $deleted['gameId']);
        $this->assertSame('Sète', $deleted['opponent']);
        $this->assertSame($team->getName(), $deleted['teamName']);
    }

    public function testImportingGamesFromCsvRecordsACreatedEntryPerGame(): void
    {
        $team = $this->aTeam()->persist();
        $csv = "team,opponent,date,venue,meetingTime,location\n"
            .sprintf("%d,Perols,23/03/2026,HOME,14:30,Gymnase A\n", $team->getId())
            .sprintf("%d,Grabels,24/03/2026,away,,\n", $team->getId());

        $superAdmin = $this->actingAsSuperAdmin();
        $this->uploadFile('/api/game/import', $this->fakeCsv($csv));
        $this->assertJsonResponse(200);

        $this->getJson('/api/game/history');
        $body = $this->assertJsonResponse(200);

        // One "created" row per imported game, all attributed to the importer.
        $this->assertSame(2, $body['total']);
        foreach ($body['data'] as $entry) {
            $this->assertSame('created', $entry['action']);
            $this->assertSame($superAdmin->getEmail(), $entry['actorEmail']);
        }
        $opponents = array_column($body['data'], 'opponent');
        sort($opponents);
        $this->assertSame(['Grabels', 'Perols'], $opponents);
    }

    public function testNullableFieldTransitionsAreRecordedBothWays(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/game', [
            'opponent'    => 'Mauguio',
            'date'        => '2026-09-22',
            'venue'       => 'away',
            'meetingTime' => null,
            'location'    => 'Gymnase A',
            'teamId'      => $team->getId(),
        ]);
        $game = $this->assertJsonResponse(200);

        // Set a meetingTime (null → value) and clear the location (value → null).
        $this->putJson('/api/game/'.$game['id'], [
            'opponent'    => 'Mauguio',
            'date'        => '2026-09-22',
            'venue'       => 'away',
            'meetingTime' => '15h30',
            'location'    => null,
            'teamId'      => $team->getId(),
        ]);
        $this->assertJsonResponse(200);

        $this->getJson('/api/game/history?gameId='.$game['id']);
        $update = $this->assertJsonResponse(200)['data'][0];

        $this->assertSame(['old' => null, 'new' => '15h30'], $update['changes']['meetingTime']);
        $this->assertSame(['old' => 'Gymnase A', 'new' => null], $update['changes']['location']);
    }

    public function testGameWrittenWithoutAnAuthenticatedUserHasNoActor(): void
    {
        // A game persisted outside an authenticated request (here, a test builder)
        // is still logged, but with no actor — the frontend shows it as "Système".
        $this->aGame()->against('Castelnau')->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/game/history');
        $body = $this->assertJsonResponse(200);

        $this->assertSame(1, $body['total']);
        $this->assertSame('created', $body['data'][0]['action']);
        $this->assertNull($body['data'][0]['actorEmail']);
    }

    // ── Read side: security ───────────────────────────────────────────────────

    public function testHistoryRequiresAuthentication(): void
    {
        $this->getJson('/api/game/history');

        $this->assertJsonResponse(401);
    }

    public function testAdminCannotSeeHistory(): void
    {
        $this->actingAsAdmin();
        $this->getJson('/api/game/history');

        $this->assertJsonResponse(403);
    }

    // ── Read side: pagination, ordering, filters ──────────────────────────────

    public function testHistoryIsPaginatedAndOrderedMostRecentFirst(): void
    {
        $base = new \DateTimeImmutable('2026-01-01 10:00:00');
        for ($i = 0; $i < 25; $i++) {
            $this->aGameHistory()
                ->forGameId(1000 + $i)
                ->at($base->modify('+'.$i.' minutes'))
                ->persist();
        }

        $this->actingAsSuperAdmin();
        $this->getJson('/api/game/history?page=1&limit=10');
        $body = $this->assertJsonResponse(200);

        $this->assertSame(25, $body['total']);
        $this->assertCount(10, $body['data']);
        // Newest (gameId 1024) comes first.
        $this->assertSame(1024, $body['data'][0]['gameId']);

        $this->getJson('/api/game/history?page=3&limit=10');
        $body = $this->assertJsonResponse(200);
        $this->assertCount(5, $body['data']);
    }

    public function testHistoryCanBeFilteredByGame(): void
    {
        $this->aGameHistory()->forGameId(42)->persist();
        $this->aGameHistory()->forGameId(42)->persist();
        $this->aGameHistory()->forGameId(99)->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/game/history?gameId=42');
        $body = $this->assertJsonResponse(200);

        $this->assertSame(2, $body['total']);
        foreach ($body['data'] as $entry) {
            $this->assertSame(42, $entry['gameId']);
        }
    }

    public function testHistoryCanBeFilteredByTeam(): void
    {
        $this->aGameHistory()->forTeamId(7)->persist();
        $this->aGameHistory()->forTeamId(8)->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/game/history?teamId=7');
        $body = $this->assertJsonResponse(200);

        $this->assertSame(1, $body['total']);
        $this->assertSame(7, $body['data'][0]['teamId']);
    }

    public function testBuilderSeededHistoryExposesExpectedShape(): void
    {
        $this->aGameHistory()
            ->action(GameHistoryAction::UPDATED)
            ->forGameId(5)
            ->forTeamId(3)
            ->withChanges(['opponent' => ['old' => 'A', 'new' => 'B']])
            ->by('coach@example.com')
            ->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/game/history');
        $body = $this->assertJsonResponse(200);

        $entry = $body['data'][0];
        $this->assertSame('updated', $entry['action']);
        $this->assertSame('coach@example.com', $entry['actorEmail']);
        $this->assertSame(['old' => 'A', 'new' => 'B'], $entry['changes']['opponent']);
        $this->assertArrayHasKey('createdAt', $entry);

        // Sanity: nothing leaked into the table beyond what we seeded.
        $this->assertSame(1, $this->em()->getRepository(GameHistory::class)->count([]));
    }
}
