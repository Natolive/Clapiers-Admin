<?php

namespace App\Tests\Functional;

use App\Entity\ContactMessage;
use App\Tests\Support\ApiTestCase;

/**
 * Routes publiques (PUBLIC_ACCESS) : /api/public/*
 */
class PublicApiTest extends ApiTestCase
{
    public function testSeasonIsPubliclyReadable(): void
    {
        $this->getJson('/api/public/season');

        $body = $this->assertJsonResponse(200);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{4}$/', $body['season']);
    }

    public function testInscriptionsStatusIsPubliclyReadableAndOpenByDefault(): void
    {
        $this->getJson('/api/public/inscriptions-status');

        $body = $this->assertJsonResponse(200);
        $this->assertTrue($body['open']);
    }

    public function testCreateContactMessagePersistsAndReturnsIt(): void
    {
        $this->postJson('/api/public/contact-message', [
            'firstName' => 'Jean',
            'lastName' => 'Dupont',
            'email' => 'jean.dupont@test.fr',
            'subject' => 'Inscription',
            'message' => 'Bonjour, je souhaite inscrire mon fils.',
            // Recaptcha is bypassed in test (RECAPTCHA_SECRET_KEY is empty)
            'recaptchaToken' => 'test-token',
        ]);

        $body = $this->assertJsonResponse(200);
        $this->assertSame('Jean', $body['firstName']);
        $this->assertSame('Inscription', $body['subject']);

        $saved = $this->em()->getRepository(ContactMessage::class)->find($body['id']);
        $this->assertNotNull($saved);
        $this->assertSame('jean.dupont@test.fr', $saved->getEmail());

        // A notification email is dispatched (the null:// transport records it)
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailAddressContains($email, 'reply-to', 'jean.dupont@test.fr');
        $this->assertEmailSubjectContains($email, 'Inscription');
        $this->assertEmailHtmlBodyContains($email, 'je souhaite inscrire mon fils');
    }

    public function testCreateContactMessageWithInvalidEmailIsRejected(): void
    {
        $this->postJson('/api/public/contact-message', [
            'firstName' => 'Jean',
            'lastName' => 'Dupont',
            'email' => 'not-an-email',
            'subject' => 'Test',
            'message' => 'Test',
            'recaptchaToken' => 'test-token',
        ]);

        $this->assertJsonResponse(422);
        $this->assertEmailCount(0);
    }

    public function testCreateContactMessageWithMissingFieldsIsRejected(): void
    {
        $this->postJson('/api/public/contact-message', ['firstName' => 'Jean']);

        $this->assertJsonResponse(422);
    }

    public function testHomeGamesReturnsOnlyUpcomingHomeGames(): void
    {
        $team = $this->aTeam()->persist();
        $upcomingHome = $this->aGame()->forTeam($team)->home()->onDate('+3 days')->persist();
        $this->aGame()->forTeam($team)->away()->onDate('+4 days')->persist();
        $this->aGame()->forTeam($team)->home()->onDate('-10 days')->persist();

        $this->getJson('/api/public/home-games');

        $body = $this->assertJsonResponse(200);
        $this->assertCount(1, $body);
        $this->assertSame($upcomingHome->getId(), $body[0]['id']);
        $this->assertSame('home', $body[0]['venue']);
    }

    public function testNationalitiesReturnsTheEnumValues(): void
    {
        $this->getJson('/api/public/nationalities');

        $body = $this->assertJsonResponse(200);
        $this->assertNotEmpty($body);
        $this->assertContains('Française', $body);
    }

    public function testClosuresAreListedOrderedByDate(): void
    {
        $this->aClosure()->from('+20 days')->to('+22 days')->because('Travaux')->persist();
        $this->aClosure()->from('+5 days')->to('+5 days')->because('Férié')->persist();

        $this->getJson('/api/public/closures');

        $body = $this->assertJsonResponse(200);
        $this->assertCount(2, $body);
        $this->assertSame('Férié', $body[0]['reason']);
        $this->assertSame('Travaux', $body[1]['reason']);
    }

    public function testTeamsAreListedSortedByName(): void
    {
        $this->aTeam()->named('Zèbres')->persist();
        $this->aTeam()->named('Aigles')->persist();

        $this->getJson('/api/public/teams');

        $body = $this->assertJsonResponse(200);
        $this->assertCount(2, $body);
        $this->assertSame('Aigles', $body[0]['name']);
        $this->assertSame('Zèbres', $body[1]['name']);
    }

    public function testGamesAreFilteredByDateRange(): void
    {
        $team = $this->aTeam()->persist();
        $inRange = $this->aGame()->forTeam($team)->onDate('2026-07-10')->persist();
        $this->aGame()->forTeam($team)->onDate('2026-09-01')->persist();

        $this->getJson('/api/public/games?start=2026-07-01&end=2026-07-31');

        $body = $this->assertJsonResponse(200);
        $this->assertCount(1, $body);
        $this->assertSame($inRange->getId(), $body[0]['id']);
    }

    public function testGamesWithoutRangeFallsBackToOneYearWindow(): void
    {
        $team = $this->aTeam()->persist();
        $this->aGame()->forTeam($team)->onDate('+2 days')->persist();
        $this->aGame()->forTeam($team)->onDate('+2 years')->persist();

        $this->getJson('/api/public/games');

        $body = $this->assertJsonResponse(200);
        $this->assertCount(1, $body);
    }

    /**
     * Régression prod : un match d'une équipe soft-supprimée faisait planter
     * toute la plage (proxy `team` introuvable → 500 sur la vue mois/année).
     */
    public function testGamesOfDeletedTeamsAreHidden(): void
    {
        $kept = $this->aTeam()->persist();
        $deleted = $this->aTeam()->persist();
        $game = $this->aGame()->forTeam($kept)->home()->onDate('+3 days')->persist();
        $this->aGame()->forTeam($deleted)->home()->onDate('+4 days')->persist();
        $this->em()->remove($deleted);
        $this->em()->flush();
        $this->em()->clear();

        $this->getJson('/api/public/games');
        $body = $this->assertJsonResponse(200);
        $this->assertSame([$game->getId()], array_column($body, 'id'));

        $this->getJson('/api/public/home-games');
        $body = $this->assertJsonResponse(200);
        $this->assertSame([$game->getId()], array_column($body, 'id'));
    }

    // ── GET /api/public/games.ics ───────────────────────────────────────────

    public function testGamesIcsExportsTimedAndAllDayEvents(): void
    {
        $team = $this->aTeam()->named('Clapiers 1')->persist();
        $date = new \DateTimeImmutable('+10 days');
        $home = $this->aGame()->forTeam($team)->home()->onDate($date)->against('VLM, Pampams; 2')
            ->withMeetingTime('20h30')->at('Gymnase Joël ABATI (Clapiers)')->persist();
        $this->aGame()->forTeam($team)->away()->onDate($date->modify('+1 day'))->against('SMDT')->persist();

        $this->client->request('GET', '/api/public/games.ics');

        $response = $this->response();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('text/calendar', (string) $response->headers->get('Content-Type'));
        $ics = (string) $response->getContent();

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        $this->assertSame(2, substr_count($ics, 'BEGIN:VEVENT'));
        $this->assertStringContainsString('UID:game-'.$home->getId().'@clapiersvb.fr', $ics);
        // Texte échappé (RFC 5545) et heure de Paris convertie en UTC
        $this->assertStringContainsString('SUMMARY:Clapiers 1 vs VLM\\, Pampams\\; 2', $ics);
        $start = new \DateTimeImmutable($date->format('Y-m-d').' 20:30', new \DateTimeZone('Europe/Paris'));
        $utc = $start->setTimezone(new \DateTimeZone('UTC'));
        $this->assertStringContainsString('DTSTART:'.$utc->format('Ymd\\THis\\Z'), $ics);
        $this->assertStringContainsString('DTEND:'.$utc->modify('+2 hours')->format('Ymd\\THis\\Z'), $ics);
        $this->assertStringContainsString('LOCATION:Gymnase Joël ABATI (Clapiers)', $ics);
        // Sans heure : évènement sur la journée
        $this->assertStringContainsString('SUMMARY:Clapiers 1 @ SMDT', $ics);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:'.$date->modify('+1 day')->format('Ymd'), $ics);
    }

    public function testGamesIcsCanBeLimitedToOneTeam(): void
    {
        $team = $this->aTeam()->named('Les Brigands')->persist();
        $this->aGame()->forTeam($team)->onDate('+3 days')->persist();
        $this->aGame()->onDate('+3 days')->persist();

        $this->client->request('GET', '/api/public/games.ics?teamId='.$team->getId());

        $ics = (string) $this->response()->getContent();
        $this->assertSame(200, $this->response()->getStatusCode(), $ics);
        $this->assertSame(1, substr_count($ics, 'BEGIN:VEVENT'));
        $this->assertStringContainsString('X-WR-CALNAME:Clapiers VB - Les Brigands', $ics);
    }

    public function testGamesIcsFoldsLongLines(): void
    {
        $this->aGame()->onDate('+3 days')->at(str_repeat('Gymnase très loin ', 10))->persist();

        $this->client->request('GET', '/api/public/games.ics');

        foreach (explode("\r\n", (string) $this->response()->getContent()) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line), $line);
        }
        // Une fois « dépliée » (CRLF + espace retirés), la ligne d'origine est intacte
        $unfolded = str_replace("\r\n ", '', (string) $this->response()->getContent());
        $this->assertStringContainsString('LOCATION:'.str_repeat('Gymnase très loin ', 10), $unfolded);
    }

    public function testGamesIcsForUnknownTeamReturns404(): void
    {
        $this->client->request('GET', '/api/public/games.ics?teamId=999999');

        $this->assertSame(404, $this->response()->getStatusCode());
    }

    public function testGamesIcsRejectsInvalidTeamId(): void
    {
        $this->client->request('GET', '/api/public/games.ics?teamId=abc');

        $this->assertSame(422, $this->response()->getStatusCode());
    }

    public function testGamesIcsAddsReminderWhenRequested(): void
    {
        $this->aGame()->onDate('+3 days')->withMeetingTime('20:30')->persist();

        $this->client->request('GET', '/api/public/games.ics?alarm=90');

        $ics = (string) $this->response()->getContent();
        $this->assertSame(200, $this->response()->getStatusCode(), $ics);
        $this->assertStringContainsString("BEGIN:VALARM\r\nACTION:DISPLAY\r\n", $ics);
        $this->assertStringContainsString('TRIGGER:-PT90M', $ics);
        $this->assertSame(1, substr_count($ics, 'END:VALARM'));
    }

    public function testGamesIcsHasNoReminderByDefault(): void
    {
        $this->aGame()->onDate('+3 days')->persist();

        $this->client->request('GET', '/api/public/games.ics');

        $this->assertStringNotContainsString('VALARM', (string) $this->response()->getContent());
    }

    public function testGamesIcsRejectsOutOfRangeReminder(): void
    {
        $this->client->request('GET', '/api/public/games.ics?alarm=0');
        $this->assertSame(422, $this->response()->getStatusCode());

        $this->client->request('GET', '/api/public/games.ics?alarm=999999');
        $this->assertSame(422, $this->response()->getStatusCode());
    }
}
