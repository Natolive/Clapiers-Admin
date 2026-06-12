<?php

namespace App\Tests\Functional;

use App\Entity\Team;
use App\Tests\Support\ApiTestCase;

/**
 * /api/team — gestion des équipes (super admin) + vue "mon équipe" (coach).
 */
class TeamApiTest extends ApiTestCase
{
    public function testSuperAdminListsAllTeams(): void
    {
        $this->aTeam()->named('Seniors M')->persist();
        $this->aTeam()->named('U18 F')->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/team');

        $body = $this->assertJsonResponse(200);
        $this->assertCount(2, $body);
    }

    public function testListingTeamsRequiresAuthentication(): void
    {
        $this->getJson('/api/team');

        $this->assertJsonResponse(401);
    }

    public function testAdminCannotListTeams(): void
    {
        $this->actingAsAdmin();
        $this->getJson('/api/team');

        $this->assertJsonResponse(403);
    }

    public function testAdminCannotCreateTeam(): void
    {
        $this->actingAsAdmin();
        $this->postJson('/api/team', ['name' => 'Interdit']);

        $this->assertJsonResponse(403);
    }

    public function testAdminCannotUpdateTeam(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsAdmin();
        $this->putJson('/api/team', ['id' => $team->getId(), 'name' => 'Interdit']);

        $this->assertJsonResponse(403);
    }

    public function testCreatingTeamWithBlankNameIsRejected(): void
    {
        $this->actingAsSuperAdmin();
        $this->postJson('/api/team', ['name' => '']);

        $this->assertJsonResponse(422);
    }

    public function testSuperAdminCreatesTeam(): void
    {
        $this->actingAsSuperAdmin();
        $this->postJson('/api/team', ['name' => 'U15 M']);

        $body = $this->assertJsonResponse(200);
        $this->assertSame('U15 M', $body['name']);

        $saved = $this->em()->getRepository(Team::class)->find($body['id']);
        $this->assertNotNull($saved);
    }

    public function testSuperAdminUpdatesTeam(): void
    {
        $team = $this->aTeam()->named('Ancien nom')->persist();

        $this->actingAsSuperAdmin();
        $this->putJson('/api/team', ['id' => $team->getId(), 'name' => 'Nouveau nom']);

        $body = $this->assertJsonResponse(200);
        $this->assertSame($team->getId(), $body['id']);
        $this->assertSame('Nouveau nom', $body['name']);
    }

    public function testMyTeamGroupsMembersByManagedTeam(): void
    {
        $teamA = $this->aTeam()->named('Équipe A')->persist();
        $teamB = $this->aTeam()->named('Équipe B')->persist();
        $otherTeam = $this->aTeam()->named('Autre équipe')->persist();

        $memberA = $this->aMember()->inTeams($teamA)->persist();
        $this->aMember()->inTeams($otherTeam)->persist();

        $coach = $this->aUser()->admin()->managing($teamA, $teamB)->persist();
        $this->actingAs($coach);

        $this->getJson('/api/team/my-team');

        $body = $this->assertJsonResponse(200);
        $this->assertCount(2, $body);

        // No guaranteed ordering of the coach's teams: index groups by name
        $groups = [];
        foreach ($body as $group) {
            $groups[$group['team']['name']] = $group['members'];
        }

        $this->assertArrayHasKey('Équipe A', $groups);
        $this->assertCount(1, $groups['Équipe A']);
        $this->assertSame($memberA->getId(), $groups['Équipe A'][0]['id']);

        $this->assertArrayHasKey('Équipe B', $groups);
        $this->assertCount(0, $groups['Équipe B']);
        $this->assertArrayNotHasKey('Autre équipe', $groups);
    }

    public function testMyTeamIsEmptyForCoachWithoutTeams(): void
    {
        $this->actingAsAdmin();
        $this->getJson('/api/team/my-team');

        $body = $this->assertJsonResponse(200);
        $this->assertSame([], $body);
    }

    public function testMyTeamIsForbiddenForPlainUser(): void
    {
        $this->actingAsUser();
        $this->getJson('/api/team/my-team');

        $this->assertJsonResponse(403);
    }

    // ── GET /api/team/my-team/license/{memberId} ────────────────────────────

    public function testCoachDownloadsLicenseOfHisTeamMember(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->withLicenseFileName('test-license.pdf')->persist();
        $this->writeLicenseFile('test-license.pdf');

        $coach = $this->aUser()->admin()->managing($team)->persist();
        $this->actingAs($coach);

        $this->getJson('/api/team/my-team/license/'.$member->getId());

        $this->assertSame(200, $this->response()->getStatusCode());
    }

    public function testCoachCannotDownloadLicenseOfAnotherTeamsMember(): void
    {
        $myTeam = $this->aTeam()->persist();
        $otherTeam = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($otherTeam)->withLicenseFileName('other-license.pdf')->persist();

        $coach = $this->aUser()->admin()->managing($myTeam)->persist();
        $this->actingAs($coach);

        $this->getJson('/api/team/my-team/license/'.$member->getId());

        $this->assertJsonResponse(403);
    }

    public function testCoachWithoutTeamCannotDownloadAnyLicense(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->persist();

        $this->actingAsAdmin();
        $this->getJson('/api/team/my-team/license/'.$member->getId());

        $this->assertJsonResponse(403);
    }

    public function testDownloadLicenseReturns404WhenMemberHasNoFile(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->persist();

        $coach = $this->aUser()->admin()->managing($team)->persist();
        $this->actingAs($coach);

        $this->getJson('/api/team/my-team/license/'.$member->getId());

        $this->assertJsonResponse(404);
    }

    public function testUpdatingUnknownTeamReturns404(): void
    {
        $this->actingAsSuperAdmin();
        $this->putJson('/api/team', ['id' => 999999, 'name' => 'Fantôme']);

        $body = $this->assertJsonResponse(404);
        $this->assertSame('Team not found', $body['message']);
    }

    public function testDownloadLicenseMissingOnDiskReturns404(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->withLicenseFileName('ghost-coach.pdf')->persist();

        $coach = $this->aUser()->admin()->managing($team)->persist();
        $this->actingAs($coach);

        $this->getJson('/api/team/my-team/license/'.$member->getId());

        $this->assertJsonResponse(404);
    }

    public function testDownloadLicenseReturns404ForUnknownMember(): void
    {
        $team = $this->aTeam()->persist();
        $coach = $this->aUser()->admin()->managing($team)->persist();
        $this->actingAs($coach);

        $this->getJson('/api/team/my-team/license/999999');

        $this->assertJsonResponse(404);
    }

    private function writeLicenseFile(string $fileName): void
    {
        $dir = static::getContainer()->getParameter('upload_directory').'/licenses';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir.'/'.$fileName, "%PDF-1.4\n%%EOF\n");
    }
}
