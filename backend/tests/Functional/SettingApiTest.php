<?php

namespace App\Tests\Functional;

use App\Tests\Support\ApiTestCase;

/**
 * Réglages (SUPER_ADMIN) : saison sportive courante.
 */
class SettingApiTest extends ApiTestCase
{
    public function testGetSeasonRequiresAuthentication(): void
    {
        $this->getJson('/api/settings/season');
        $this->assertJsonResponse(401);
    }

    public function testGetSeasonIsForbiddenForAdmin(): void
    {
        $this->actingAsAdmin();
        $this->getJson('/api/settings/season');
        $this->assertJsonResponse(403);
    }

    public function testGetSeasonReturnsCurrentAndSuggestion(): void
    {
        $this->actingAsSuperAdmin();
        $this->getJson('/api/settings/season');

        $body = $this->assertJsonResponse(200);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{4}$/', $body['season']);
        // Non défini : la saison courante = la suggestion calculée.
        $this->assertSame($body['suggestion'], $body['season']);
    }

    public function testSetSeasonPersistsAndIsReadBack(): void
    {
        $this->actingAsSuperAdmin();

        $this->putJson('/api/settings/season', ['season' => '2030-2031']);
        $this->assertSame('2030-2031', $this->assertJsonResponse(200)['season']);

        $this->getJson('/api/settings/season');
        $this->assertSame('2030-2031', $this->assertJsonResponse(200)['season']);

        // Deuxième écriture : mise à jour du réglage existant.
        $this->putJson('/api/settings/season', ['season' => '2031-2032']);
        $this->assertSame('2031-2032', $this->assertJsonResponse(200)['season']);
    }

    public function testSetSeasonRejectsBadFormat(): void
    {
        $this->actingAsSuperAdmin();
        $this->putJson('/api/settings/season', ['season' => 'pas-une-saison']);
        $this->assertJsonResponse(422);
    }

    public function testSetSeasonRejectsNonConsecutiveYears(): void
    {
        $this->actingAsSuperAdmin();
        $this->putJson('/api/settings/season', ['season' => '2030-2032']);
        $this->assertJsonResponse(422);
    }

    public function testSetSeasonIsForbiddenForAdmin(): void
    {
        $this->actingAsAdmin();
        $this->putJson('/api/settings/season', ['season' => '2030-2031']);
        $this->assertJsonResponse(403);
    }

    public function testSubmittedLicenseUsesConfiguredSeason(): void
    {
        $this->actingAsSuperAdmin();
        $this->putJson('/api/settings/season', ['season' => '2040-2041']);
        $this->assertJsonResponse(200);

        $this->postJson('/api/public/license-request', [
            'firstName' => 'Marie',
            'lastName' => 'Curie',
            'phoneNumber' => '+33612345678',
            'email' => 'marie.saison@test.fr',
            'addressStreet' => '1 rue X',
            'addressZip' => '34000',
            'addressCity' => 'Montpellier',
            'gender' => 'female',
            'birthDate' => '2000-05-01',
            'nationality' => 'Française',
            'recaptchaToken' => 'test-token',
        ]);

        $this->assertSame('2040-2041', $this->assertJsonResponse(200)['season']);
    }
}
