<?php

namespace App\Tests\Functional;

use App\Common\Service\SeasonProvider;
use App\Entity\Enum\LicenseStatus;
use App\Tests\Support\ApiTestCase;

/**
 * GET /api/stats/dashboard — réservé au super admin.
 */
class StatsApiTest extends ApiTestCase
{
    public function testDashboardReturnsMembersGamesAndTeamsStats(): void
    {
        $team = $this->aTeam()->persist();
        $this->aMember()->inTeams($team)->persist();
        $this->aGame()->forTeam($team)->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/stats/dashboard');

        $body = $this->assertJsonResponse(200);
        $this->assertArrayHasKey('members', $body);
        $this->assertArrayHasKey('games', $body);
        $this->assertArrayHasKey('teams', $body);
        $this->assertSame(1, $body['teams']['total']);
    }

    public function testDashboardAggregatesLicensesUsersAndMessages(): void
    {
        $season = static::getContainer()->get(SeasonProvider::class)->current();
        $this->aLicense()->withStatus(LicenseStatus::SOUMISE)->inSeason($season)->persist();
        $this->aLicense()->withStatus(LicenseStatus::PAYEE)->inSeason($season)->persist();
        $this->aUser()->persist(); // en plus du super admin authentifié
        $this->aContactMessage()->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/stats/dashboard');

        $body = $this->assertJsonResponse(200);

        $this->assertSame(2, $body['licenses']['total']);
        $this->assertSame(1, $body['licenses']['byStatus']['soumise']);
        $this->assertSame(1, $body['licenses']['byStatus']['payee']);
        $this->assertSame(0, $body['licenses']['byStatus']['refusee']);

        // le super admin (acting) + l'utilisateur créé ci-dessus
        $this->assertSame(2, $body['users']['total']);
        $this->assertSame(1, $body['messages']['total']);
    }

    public function testDashboardScopesToRequestedSeason(): void
    {
        $this->aLicense()->inSeason('2030-2031')->withStatus(LicenseStatus::SOUMISE)->persist();
        $this->aLicense()->inSeason('2030-2031')->withStatus(LicenseStatus::PAYEE)->persist();
        $this->aLicense()->inSeason('2031-2032')->withStatus(LicenseStatus::SOUMISE)->persist();

        $this->actingAsSuperAdmin();

        $this->getJson('/api/stats/dashboard?season=2030-2031');
        $this->assertSame(2, $this->assertJsonResponse(200)['licenses']['total']);

        $this->getJson('/api/stats/dashboard?season=2031-2032');
        $this->assertSame(1, $this->assertJsonResponse(200)['licenses']['total']);
    }

    public function testNewThisSeasonCountsFirstTimeMembersRegardlessOfCreatedAt(): void
    {
        // Nouveau : licence validée cette saison, aucune licence antérieure.
        // createdAt hors de la fenêtre calendaire (inscription avant septembre) :
        // il doit tout de même compter comme nouveau.
        $newcomer = $this->aMember()->licensedFor('2030-2031')->persist();
        $this->backdateMember($newcomer, '2030-07-01');

        // Renouvellement : licence cette saison ET une saison antérieure → pas nouveau.
        $returning = $this->aMember()->licensedFor('2030-2031')->persist();
        $this->aLicense()->forMember($returning)->inSeason('2029-2030')
            ->withStatus(LicenseStatus::VALIDEE)->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/stats/dashboard?season=2030-2031');
        $body = $this->assertJsonResponse(200);

        // Les deux sont licenciés (total) ; un seul est une première adhésion.
        $this->assertSame(2, $body['members']['total']);
        $this->assertSame(1, $body['members']['createdAt']['newThisSeason']);
    }

    private function backdateMember(\App\Entity\Member $member, string $date): void
    {
        $this->em()->createQuery('UPDATE App\Entity\Member m SET m.createdAt = :d WHERE m.id = :id')
            ->execute(['d' => new \DateTimeImmutable($date), 'id' => $member->getId()]);
    }

    public function testAdminIsForbidden(): void
    {
        $this->actingAsAdmin();
        $this->getJson('/api/stats/dashboard');

        $this->assertJsonResponse(403);
    }

    public function testUnauthenticatedIsRejected(): void
    {
        $this->getJson('/api/stats/dashboard');

        $this->assertJsonResponse(401);
    }
}
