<?php

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Tests\Support\ApiTestCase;

/**
 * /api/game — lecture/écriture admin (limitée à ses équipes) et import CSV (super admin).
 */
class GameApiTest extends ApiTestCase
{
    public function testAdminListsGamesWithDateRange(): void
    {
        $team = $this->aTeam()->persist();
        $inRange = $this->aGame()->forTeam($team)->onDate('2026-07-10')->persist();
        $this->aGame()->forTeam($team)->onDate('2026-10-10')->persist();

        $this->actingAsAdmin();
        $this->getJson('/api/game?start=2026-07-01&end=2026-07-31');

        $body = $this->assertJsonResponse(200);
        $this->assertCount(1, $body);
        $this->assertSame($inRange->getId(), $body[0]['id']);
    }

    public function testGamesCanBeFilteredByTeam(): void
    {
        $teamA = $this->aTeam()->persist();
        $teamB = $this->aTeam()->persist();
        $gameA = $this->aGame()->forTeam($teamA)->onDate('2026-07-10')->persist();
        $this->aGame()->forTeam($teamB)->onDate('2026-07-11')->persist();

        $this->actingAsAdmin();
        $this->getJson('/api/game?teamId='.$teamA->getId().'&start=2026-07-01&end=2026-07-31');

        $body = $this->assertJsonResponse(200);
        $this->assertCount(1, $body);
        $this->assertSame($gameA->getId(), $body[0]['id']);
    }

    public function testFilteringByUnknownTeamReturns404(): void
    {
        $this->actingAsAdmin();
        $this->getJson('/api/game?teamId=999999');

        $this->assertJsonResponse(404);
    }

    public function testPlainUserCannotListGames(): void
    {
        $this->actingAsUser();
        $this->getJson('/api/game');

        $this->assertJsonResponse(403);
    }

    // ── POST /api/game ──────────────────────────────────────────────────────

    public function testSuperAdminCreatesGameForAnyTeam(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/game', [
            'opponent' => 'Montpellier VB',
            'date' => '2026-09-12',
            'meetingTime' => '14h00',
            'venue' => 'home',
            'location' => 'Gymnase de Clapiers',
            'teamId' => $team->getId(),
        ]);

        $body = $this->assertJsonResponse(200);
        $this->assertSame('Montpellier VB', $body['opponent']);
        $this->assertSame($team->getId(), $body['team']['id']);

        $this->assertNotNull($this->em()->getRepository(Game::class)->find($body['id']));
    }

    public function testCoachCreatesGameForHisOwnTeam(): void
    {
        $team = $this->aTeam()->persist();
        $coach = $this->aUser()->admin()->managing($team)->persist();

        $this->actingAs($coach);
        $this->postJson('/api/game', [
            'opponent' => 'Nîmes',
            'date' => '2026-09-13',
            'venue' => 'away',
            'teamId' => $team->getId(),
        ]);

        $this->assertJsonResponse(200);
    }

    public function testCoachCannotCreateGameForAnotherTeam(): void
    {
        $myTeam = $this->aTeam()->persist();
        $otherTeam = $this->aTeam()->persist();
        $coach = $this->aUser()->admin()->managing($myTeam)->persist();

        $this->actingAs($coach);
        $this->postJson('/api/game', [
            'opponent' => 'Nîmes',
            'date' => '2026-09-13',
            'teamId' => $otherTeam->getId(),
        ]);

        $this->assertJsonResponse(403);
    }

    public function testSingleTeamCoachCanOmitTeamId(): void
    {
        $team = $this->aTeam()->persist();
        $coach = $this->aUser()->admin()->managing($team)->persist();

        $this->actingAs($coach);
        $this->postJson('/api/game', [
            'opponent' => 'Sète',
            'date' => '2026-09-14',
        ]);

        $body = $this->assertJsonResponse(200);
        $this->assertSame($team->getId(), $body['team']['id']);
    }

    public function testSuperAdminMustProvideTeamId(): void
    {
        $this->actingAsSuperAdmin();
        $this->postJson('/api/game', [
            'opponent' => 'Sète',
            'date' => '2026-09-14',
        ]);

        $body = $this->assertJsonResponse(400);
        $this->assertSame('Team is required', $body['message']);
    }

    public function testTeamCannotHaveTwoGamesOnTheSameDay(): void
    {
        $team = $this->aTeam()->persist();
        $this->aGame()->forTeam($team)->onDate('2026-09-15')->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/game', [
            'opponent' => 'Béziers',
            'date' => '2026-09-15',
            'teamId' => $team->getId(),
        ]);

        $this->assertJsonResponse(422);
    }

    public function testAtMostThreeHomeGamesPerDay(): void
    {
        foreach (range(1, 3) as $i) {
            $this->aGame()->forTeam($this->aTeam()->persist())->home()->onDate('2026-09-16')->persist();
        }
        $fourthTeam = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/game', [
            'opponent' => 'Lattes',
            'date' => '2026-09-16',
            'venue' => 'home',
            'teamId' => $fourthTeam->getId(),
        ]);

        $this->assertJsonResponse(422);

        // The same game played away is fine
        $this->postJson('/api/game', [
            'opponent' => 'Lattes',
            'date' => '2026-09-16',
            'venue' => 'away',
            'teamId' => $fourthTeam->getId(),
        ]);

        $this->assertJsonResponse(200);
    }

    public function testCreateGameWithBlankOpponentIsRejected(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/game', [
            'opponent' => '',
            'date' => '2026-09-17',
            'teamId' => $team->getId(),
        ]);

        $this->assertJsonResponse(422);
    }

    // ── PUT /api/game/{id} ──────────────────────────────────────────────────

    public function testSuperAdminUpdatesGame(): void
    {
        $team = $this->aTeam()->persist();
        $game = $this->aGame()->forTeam($team)->onDate('2026-09-18')->persist();

        $this->actingAsSuperAdmin();
        $this->putJson('/api/game/'.$game->getId(), [
            'opponent' => 'Nouvel adversaire',
            'date' => '2026-09-19',
            'venue' => 'away',
            'teamId' => $team->getId(),
        ]);

        $body = $this->assertJsonResponse(200);
        $this->assertSame('Nouvel adversaire', $body['opponent']);
        $this->assertSame('2026-09-19', $body['date']);
        $this->assertSame('away', $body['venue']);
    }

    public function testCoachCannotHijackAnotherTeamsGameViaUpdate(): void
    {
        $myTeam = $this->aTeam()->persist();
        $otherTeam = $this->aTeam()->persist();
        $game = $this->aGame()->forTeam($otherTeam)->onDate('2026-09-21')->persist();
        $coach = $this->aUser()->admin()->managing($myTeam)->persist();

        // teamId points to his own team, but the game belongs to another one
        $this->actingAs($coach);
        $this->putJson('/api/game/'.$game->getId(), [
            'opponent' => 'Détourné',
            'date' => '2026-09-22',
            'teamId' => $myTeam->getId(),
        ]);

        $this->assertJsonResponse(403);
    }

    public function testUpdateUnknownGameReturns404(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->putJson('/api/game/999999', [
            'opponent' => 'X',
            'date' => '2026-09-20',
            'teamId' => $team->getId(),
        ]);

        $this->assertJsonResponse(404);
    }

    // ── DELETE /api/game/{id} ───────────────────────────────────────────────

    public function testCoachDeletesGameOfHisTeam(): void
    {
        $team = $this->aTeam()->persist();
        $game = $this->aGame()->forTeam($team)->persist();
        $coach = $this->aUser()->admin()->managing($team)->persist();
        // Doctrine nulls the id of the in-memory instance on deletion: keep a copy
        $gameId = $game->getId();

        $this->actingAs($coach);
        $this->deleteJson('/api/game/'.$gameId);

        $this->assertJsonResponse(200);
        $this->assertNull($this->em()->getRepository(Game::class)->find($gameId));
    }

    public function testCoachCannotDeleteGameOfAnotherTeam(): void
    {
        $otherTeam = $this->aTeam()->persist();
        $game = $this->aGame()->forTeam($otherTeam)->persist();
        $coach = $this->aUser()->admin()->managing($this->aTeam()->persist())->persist();

        $this->actingAs($coach);
        $this->deleteJson('/api/game/'.$game->getId());

        $this->assertJsonResponse(403);
        $this->assertNotNull($this->em()->getRepository(Game::class)->find($game->getId()));
    }

    public function testDeleteUnknownGameReturns404(): void
    {
        $this->actingAsSuperAdmin();
        $this->deleteJson('/api/game/999999');

        $this->assertJsonResponse(404);
    }

    // ── POST /api/game/import ───────────────────────────────────────────────

    public function testSuperAdminImportsGamesFromCsv(): void
    {
        $team = $this->aTeam()->persist();
        $csv = "team,opponent,date,venue,meetingTime,location\n"
            .sprintf("%d,Perols,23/03/2026,HOME,14:30,Gymnase A\n", $team->getId())
            .sprintf("%d,Grabels,24/03/2026,away,,\n", $team->getId());

        $this->actingAsSuperAdmin();
        $this->uploadFile('/api/game/import', $this->fakeCsv($csv));

        $body = $this->assertJsonResponse(200);
        $this->assertSame(2, $body['imported']);
    }

    /**
     * Chaque règle de validation de ligne CSV produit un 422 avec un message ciblé.
     * "%TEAM%" est remplacé par l'id d'une équipe valide créée au runtime.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidCsvRowProvider')]
    public function testImportRejectsInvalidRow(string $csvTemplate, string $expectedMessagePart): void
    {
        $team = $this->aTeam()->persist();
        $csv = str_replace('%TEAM%', (string) $team->getId(), $csvTemplate);

        $this->actingAsSuperAdmin();
        $this->uploadFile('/api/game/import', $this->fakeCsv($csv));

        $body = $this->assertJsonResponse(422);
        $this->assertStringContainsString($expectedMessagePart, $body['message']);
    }

    public static function invalidCsvRowProvider(): iterable
    {
        $headers = "team,opponent,date,venue,meetingTime,location\n";

        yield 'en-tête manquant' => [
            "team,opponent,date\n%TEAM%,Perols,23/03/2026\n",
            'En-tête(s) manquant(s)',
        ];
        yield 'team non numérique' => [
            $headers."abc,Perols,23/03/2026,HOME,,\n",
            '"team" doit être un entier positif',
        ];
        yield 'team inconnue' => [
            $headers."999999,Perols,23/03/2026,HOME,,\n",
            'aucune équipe trouvée',
        ];
        yield 'opponent vide' => [
            $headers."%TEAM%,,23/03/2026,HOME,,\n",
            '"opponent" est requis',
        ];
        yield 'opponent trop long' => [
            $headers.'%TEAM%,'.str_repeat('a', 256).",23/03/2026,HOME,,\n",
            '"opponent" dépasse 255 caractères',
        ];
        yield 'date au mauvais format' => [
            $headers."%TEAM%,Perols,2026-03-23,HOME,,\n",
            '"date" doit être au format JJ/MM/AAAA',
        ];
        yield 'venue invalide' => [
            $headers."%TEAM%,Perols,23/03/2026,NEUTRAL,,\n",
            '"venue" doit être "HOME" ou "AWAY"',
        ];
        yield 'meetingTime trop long' => [
            $headers."%TEAM%,Perols,23/03/2026,HOME,12:30:45:99999,\n",
            '"meetingTime" dépasse 10 caractères',
        ];
        yield 'location trop longue' => [
            $headers.'%TEAM%,Perols,23/03/2026,HOME,,'.str_repeat('a', 256)."\n",
            '"location" dépasse 255 caractères',
        ];
    }

    public function testImportRollsBackEntirelyOnInvalidRow(): void
    {
        $team = $this->aTeam()->persist();
        $csv = "team,opponent,date,venue,meetingTime,location\n"
            .sprintf("%d,Perols,23/03/2026,HOME,,\n", $team->getId())
            ."999999,Inconnu,24/03/2026,HOME,,\n";

        $this->actingAsSuperAdmin();
        $this->uploadFile('/api/game/import', $this->fakeCsv($csv));

        $this->assertGreaterThanOrEqual(400, $this->response()->getStatusCode());
        $this->assertSame(
            0,
            $this->em()->getRepository(Game::class)->count([]),
            'No game should be persisted when one row is invalid',
        );
    }

    public function testImportEmptyCsvIsRejected(): void
    {
        $this->actingAsSuperAdmin();
        $this->uploadFile('/api/game/import', $this->fakeCsv("team,opponent,date,venue,meetingTime,location\n"));

        $this->assertJsonResponse(422);
    }

    public function testImportWithoutFileIsRejected(): void
    {
        $this->actingAsSuperAdmin();
        $this->requestJson('POST', '/api/game/import');

        $this->assertJsonResponse(422);
    }

    public function testAdminCannotImportGames(): void
    {
        $this->actingAsAdmin();
        $this->uploadFile('/api/game/import', $this->fakeCsv("team,opponent,date,venue,meetingTime,location\n"));

        $this->assertJsonResponse(403);
    }
}
