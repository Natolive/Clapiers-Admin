<?php

namespace App\Tests\Functional;

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
