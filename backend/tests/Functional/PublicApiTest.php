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
}
