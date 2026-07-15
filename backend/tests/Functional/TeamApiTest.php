<?php

namespace App\Tests\Functional;

use App\Common\Service\MemberMediaSeeder;
use App\Common\Service\SeasonProvider;
use App\Entity\Member;
use App\Entity\Team;
use App\Repository\MemberDocumentRepository;
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

    public function testAdminCannotCreateOrUpdateTeam(): void
    {
        $this->actingAsAdmin();
        $this->postJson('/api/team', ['name' => 'Interdit']);
        $this->assertJsonResponse(403);
    }

    // ── Affectation des coachs depuis l'équipe ──────────────────────────────

    public function testCreateTeamWithCoaches(): void
    {
        $coachA = $this->aUser()->admin()->persist();
        $coachB = $this->aUser()->admin()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/team', [
            'name' => 'Seniors',
            'userIds' => [$coachA->getId(), $coachB->getId()],
        ]);

        $body = $this->assertJsonResponse(200);
        $coachIds = array_column($body['coaches'], 'id');
        sort($coachIds);
        $expected = [$coachA->getId(), $coachB->getId()];
        sort($expected);
        $this->assertSame($expected, $coachIds);

        // Le coach voit désormais l'équipe dans "Mon équipe".
        $this->actingAs($coachA);
        $this->getJson('/api/team/my-team');
        $myTeam = $this->assertJsonResponse(200);
        $this->assertSame('Seniors', $myTeam[0]['team']['name']);
    }

    public function testUpdateTeamReplacesCoaches(): void
    {
        $team = $this->aTeam()->persist();
        $former = $this->aUser()->admin()->managing($team)->persist();
        $incoming = $this->aUser()->admin()->persist();

        $this->actingAsSuperAdmin();
        $this->putJson('/api/team', [
            'id' => $team->getId(),
            'name' => $team->getName(),
            'userIds' => [$incoming->getId()],
        ]);

        $body = $this->assertJsonResponse(200);
        $coachIds = array_column($body['coaches'], 'id');
        $this->assertSame([$incoming->getId()], $coachIds);

        // L'ancien coach n'a plus l'équipe.
        $this->em()->clear();
        $this->actingAs($former);
        $this->getJson('/api/team/my-team');
        $this->assertSame([], $this->assertJsonResponse(200));
    }

    public function testUpdateTeamWithEmptyUserIdsRemovesAllCoaches(): void
    {
        $team = $this->aTeam()->persist();
        $this->aUser()->admin()->managing($team)->persist();

        $this->actingAsSuperAdmin();
        $this->putJson('/api/team', [
            'id' => $team->getId(),
            'name' => $team->getName(),
            'userIds' => [],
        ]);

        $body = $this->assertJsonResponse(200);
        $this->assertSame([], $body['coaches']);
    }

    public function testUpdateTeamWithoutUserIdsKeepsCoaches(): void
    {
        $team = $this->aTeam()->persist();
        $coach = $this->aUser()->admin()->managing($team)->persist();

        $this->actingAsSuperAdmin();
        // Pas de clé userIds → on ne touche pas aux coachs.
        $this->putJson('/api/team', ['id' => $team->getId(), 'name' => 'Renommée']);

        $body = $this->assertJsonResponse(200);
        $this->assertSame([$coach->getId()], array_column($body['coaches'], 'id'));
    }

    public function testCreateTeamWithUnknownCoachReturns404(): void
    {
        $this->actingAsSuperAdmin();
        $this->postJson('/api/team', ['name' => 'X', 'userIds' => [999999]]);

        $body = $this->assertJsonResponse(404);
        $this->assertSame('User 999999 not found', $body['message']);
    }

    public function testSuperAdminCanBeAssignedAsCoach(): void
    {
        $superCoach = $this->aUser()->superAdmin()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/team', ['name' => 'Élite', 'userIds' => [$superCoach->getId()]]);

        $body = $this->assertJsonResponse(200);
        $this->assertSame([$superCoach->getId()], array_column($body['coaches'], 'id'));
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
        $member = $this->aMember()->inTeams($team)->persist();
        $this->giveLicenseInMediatheque($member);

        $coach = $this->aUser()->admin()->managing($team)->persist();
        $this->actingAs($coach);

        $this->getJson('/api/team/my-team/license/'.$member->getId());

        $this->assertSame(200, $this->response()->getStatusCode());
    }

    public function testCoachCannotDownloadLicenseOfAnotherTeamsMember(): void
    {
        $myTeam = $this->aTeam()->persist();
        $otherTeam = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($otherTeam)->persist();
        $this->giveLicenseInMediatheque($member);

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

    public function testDownloadLicenseReturns404ForUnknownMember(): void
    {
        $team = $this->aTeam()->persist();
        $coach = $this->aUser()->admin()->managing($team)->persist();
        $this->actingAs($coach);

        $this->getJson('/api/team/my-team/license/999999');

        $this->assertJsonResponse(404);
    }

    public function testDownloadLicenseReturns404WhenFileMissingOnDisk(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->persist();
        // Slot renseigné mais fichier absent du disque.
        $season = static::getContainer()->get(SeasonProvider::class)->current();
        static::getContainer()->get(MemberMediaSeeder::class)->ensureSeason($member, $season);
        $this->em()->flush();
        $slot = static::getContainer()->get(MemberDocumentRepository::class)
            ->findDefaultSlot($member, $season, 'license');
        $slot->setFile('ghost.pdf', 'licence.pdf', 'application/pdf', 12);
        $this->em()->flush();

        $coach = $this->aUser()->admin()->managing($team)->persist();
        $this->actingAs($coach);

        $this->getJson('/api/team/my-team/license/'.$member->getId());

        $this->assertJsonResponse(404);
    }

    // ── GET /api/team/my-team/member/{memberId}/profile-picture ─────────────

    public function testCoachSeesPhotoOfHisTeamMember(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->persist();
        $this->givePhotoInMediatheque($member);

        $coach = $this->aUser()->admin()->managing($team)->persist();
        $this->actingAs($coach);

        $this->getJson('/api/team/my-team/member/'.$member->getId().'/profile-picture');

        $this->assertSame(200, $this->response()->getStatusCode());
    }

    public function testCoachCannotSeePhotoOfAnotherTeamsMember(): void
    {
        $myTeam = $this->aTeam()->persist();
        $otherTeam = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($otherTeam)->persist();
        $this->givePhotoInMediatheque($member);

        $coach = $this->aUser()->admin()->managing($myTeam)->persist();
        $this->actingAs($coach);

        $this->getJson('/api/team/my-team/member/'.$member->getId().'/profile-picture');

        $this->assertJsonResponse(403);
    }

    public function testCoachPhotoRouteIsForbiddenForPlainUser(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->persist();

        $this->actingAsUser();
        $this->getJson('/api/team/my-team/member/'.$member->getId().'/profile-picture');

        $this->assertJsonResponse(403);
    }

    public function testCoachPhotoReturns404WhenNoPhoto(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->persist();

        $coach = $this->aUser()->admin()->managing($team)->persist();
        $this->actingAs($coach);

        $this->getJson('/api/team/my-team/member/'.$member->getId().'/profile-picture');

        $this->assertJsonResponse(404);
    }

    public function testCoachPhotoReturns404ForUnknownMember(): void
    {
        $team = $this->aTeam()->persist();
        $coach = $this->aUser()->admin()->managing($team)->persist();
        $this->actingAs($coach);

        $this->getJson('/api/team/my-team/member/999999/profile-picture');

        $this->assertJsonResponse(404);
    }

    /** Remplit le slot licence de la saison courante dans la médiathèque du membre. */
    private function giveLicenseInMediatheque(Member $member): void
    {
        $season = static::getContainer()->get(SeasonProvider::class)->current();
        static::getContainer()->get(MemberMediaSeeder::class)->ensureSeason($member, $season);
        $this->em()->flush();
        $slot = static::getContainer()->get(MemberDocumentRepository::class)
            ->findDefaultSlot($member, $season, 'license');
        $this->attachFile($slot, 'lic.pdf', 'licence.pdf');
    }

    /** Remplit le slot photo de profil dans la médiathèque du membre. */
    private function givePhotoInMediatheque(Member $member): void
    {
        static::getContainer()->get(MemberMediaSeeder::class)->ensureRootFolders($member);
        $this->em()->flush();
        $slot = static::getContainer()->get(MemberDocumentRepository::class)
            ->findRootDocumentSlot($member, 'identity_photo');
        $this->attachFile($slot, 'pp.png', 'photo.png');
    }

    private function attachFile(?object $slot, string $storedName, string $originalName): void
    {
        $this->assertNotNull($slot);
        $dir = static::getContainer()->getParameter('upload_directory').'/member-media';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir.'/'.$storedName, "%PDF-1.4\n%%EOF\n");
        $slot->setFile($storedName, $originalName, 'application/octet-stream', 12);
        $this->em()->flush();
    }
}
