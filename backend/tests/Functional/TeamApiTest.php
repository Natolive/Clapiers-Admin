<?php

namespace App\Tests\Functional;

use App\Common\Service\MemberMediaSeeder;
use App\Entity\AppUser;
use App\Entity\Enum\LicenseStatus;
use App\Entity\Enum\MemberStatus;
use App\Entity\Game;
use App\Common\Service\SeasonProvider;
use App\Entity\Member;
use App\Entity\Team;
use App\Repository\MemberDocumentRepository;
use App\Tests\Support\Fake\FakeBunnyStorageClient;
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

    // ── Compteurs d'effectif dans la liste ──────────────────────────────────

    public function testTeamListingCarriesSeasonCounters(): void
    {
        $season = $this->currentSeason();
        $team = $this->aTeam()->named('Seniors M')->persist();
        $this->aTeam()->named('Équipe vide')->persist();

        $paid = $this->aMember()->inTeams($team)->licensedFor($season, LicenseStatus::PAYEE)->persist();
        $paid->setStatus(MemberStatus::ACTIVE); // le builder de licence l'avait passé en attente
        $this->aMember()->inTeams($team)->licensedFor($season)->persist(); // licencié mais non payé
        $this->em()->flush();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/team');

        $body = $this->assertJsonResponse(200);
        $counters = array_column($body, null, 'name');

        $this->assertSame(2, $counters['Seniors M']['memberCount']);
        $this->assertSame(1, $counters['Seniors M']['paidCount']);
        $this->assertSame(0, $counters['Équipe vide']['memberCount']);
        $this->assertSame($season, $counters['Seniors M']['season']);
    }

    public function testTeamListingCountersAreScopedToRequestedSeason(): void
    {
        $team = $this->aTeam()->named('Seniors M')->persist();
        $this->aMember()->inTeams($team)->licensedFor('2000-2001')->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/team?season=2000-2001');

        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body[0]['memberCount']);

        $this->getJson('/api/team');
        $body = $this->assertJsonResponse(200);
        $this->assertSame(0, $body[0]['memberCount']);
    }

    /**
     * Symfony refuse une query string invalide avec un 404 (comportement par
     * défaut de #[MapQueryString], partagé par les autres endpoints saisonniers).
     */
    public function testTeamListingRejectsInvalidSeason(): void
    {
        $this->actingAsSuperAdmin();
        $this->getJson('/api/team?season=pas-une-saison');

        $this->assertJsonResponse(404);
    }

    // ── DELETE /api/team/{id} ───────────────────────────────────────────────

    public function testSuperAdminDeletesTeam(): void
    {
        $team = $this->aTeam()->named('À supprimer')->persist();
        $teamId = $team->getId();

        $this->actingAsSuperAdmin();
        $this->deleteJson('/api/team/'.$teamId);

        $body = $this->assertJsonResponse(200);
        $this->assertTrue($body['deleted']);

        // Suppression douce : la ligne existe encore mais le filtre la masque.
        $this->getJson('/api/team');
        $this->assertSame([], $this->assertJsonResponse(200));
    }

    public function testDeletingTeamDetachesCoachesAndMembers(): void
    {
        $season = $this->currentSeason();
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->licensedFor($season)->persist();
        $coach = $this->aUser()->admin()->managing($team)->persist();

        $this->actingAsSuperAdmin();
        $this->deleteJson('/api/team/'.$team->getId());
        $this->assertJsonResponse(200);

        $this->em()->clear();
        $this->assertCount(0, $this->em()->getRepository(Member::class)->find($member->getId())->getTeams());
        $this->assertCount(0, $this->em()->getRepository(AppUser::class)->find($coach->getId())->getTeams());
    }

    public function testDeletingTeamKeepsItsGames(): void
    {
        $team = $this->aTeam()->persist();
        $game = $this->aGame()->forTeam($team)->persist();

        $this->actingAsSuperAdmin();
        $this->deleteJson('/api/team/'.$team->getId());

        $this->assertJsonResponse(200);
        $this->em()->clear();
        $this->assertNotNull($this->em()->getRepository(Game::class)->find($game->getId()));
    }

    public function testDeletingUnknownTeamReturns404(): void
    {
        $this->actingAsSuperAdmin();
        $this->deleteJson('/api/team/999999');

        $this->assertJsonResponse(404);
    }

    public function testDeletingTeamTwiceReturns404(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->deleteJson('/api/team/'.$team->getId());
        $this->assertJsonResponse(200);

        $this->deleteJson('/api/team/'.$team->getId());
        $this->assertJsonResponse(404);
    }

    public function testDeletingTeamRequiresAuthentication(): void
    {
        $team = $this->aTeam()->persist();

        $this->deleteJson('/api/team/'.$team->getId());

        $this->assertJsonResponse(401);
    }

    public function testAdminCannotDeleteTeam(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsAdmin();
        $this->deleteJson('/api/team/'.$team->getId());

        $this->assertJsonResponse(403);
    }

    // ── PATCH /api/team/{id}/members (effectif) ─────────────────────────────

    public function testSuperAdminAddsMembersToTeam(): void
    {
        $season = $this->currentSeason();
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->named('Ajouté', 'Effectif')->licensedFor($season)->persist();

        $this->actingAsSuperAdmin();
        $this->patchJson('/api/team/'.$team->getId().'/members', ['add' => [$member->getId()]]);

        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['memberCount']);
        $this->assertSame([$member->getId()], array_column($body['members'], 'id'));
    }

    public function testSuperAdminRemovesMemberFromTeam(): void
    {
        $season = $this->currentSeason();
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->licensedFor($season)->persist();

        $this->actingAsSuperAdmin();
        $this->patchJson('/api/team/'.$team->getId().'/members', ['remove' => [$member->getId()]]);

        $body = $this->assertJsonResponse(200);
        $this->assertSame(0, $body['memberCount']);
        $this->assertSame([], $body['members']);
    }

    /**
     * Le rattachement n'est pas saisonnier : un licencié ajouté sans licence
     * pour la saison affichée est bien rattaché, simplement absent de
     * l'effectif renvoyé. Le front en avertit l'utilisateur.
     */
    public function testMemberWithoutCurrentLicenseIsAttachedButNotListed(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->licensedFor('2000-2001')->persist();

        $this->actingAsSuperAdmin();
        $this->patchJson('/api/team/'.$team->getId().'/members', ['add' => [$member->getId()]]);

        $body = $this->assertJsonResponse(200);
        $this->assertSame([], $body['members']);

        $this->em()->clear();
        $this->assertCount(1, $this->em()->getRepository(Member::class)->find($member->getId())->getTeams());
    }

    public function testRosterUpdateIsIdempotent(): void
    {
        $season = $this->currentSeason();
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->licensedFor($season)->persist();

        $this->actingAsSuperAdmin();
        $this->patchJson('/api/team/'.$team->getId().'/members', ['add' => [$member->getId(), $member->getId()]]);

        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['memberCount']);
    }

    public function testRosterUpdateOnUnknownTeamReturns404(): void
    {
        $this->actingAsSuperAdmin();
        $this->patchJson('/api/team/999999/members', ['add' => []]);

        $this->assertJsonResponse(404);
    }

    public function testRosterUpdateWithUnknownMemberReturns404(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->patchJson('/api/team/'.$team->getId().'/members', ['add' => [999999]]);

        $body = $this->assertJsonResponse(404);
        $this->assertSame('Member 999999 not found', $body['message']);
    }

    public function testRosterUpdateRejectsNonIntegerIds(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->patchJson('/api/team/'.$team->getId().'/members', ['add' => ['pas-un-id']]);

        $this->assertJsonResponse(422);
    }

    public function testRosterUpdateRequiresAuthentication(): void
    {
        $team = $this->aTeam()->persist();

        $this->patchJson('/api/team/'.$team->getId().'/members', ['add' => []]);

        $this->assertJsonResponse(401);
    }

    public function testAdminCannotUpdateRoster(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsAdmin();
        $this->patchJson('/api/team/'.$team->getId().'/members', ['add' => []]);

        $this->assertJsonResponse(403);
    }

    private function currentSeason(): string
    {
        return static::getContainer()->get(SeasonProvider::class)->current();
    }

    public function testMyTeamGroupsMembersByManagedTeam(): void
    {
        $teamA = $this->aTeam()->named('Équipe A')->persist();
        $teamB = $this->aTeam()->named('Équipe B')->persist();
        $otherTeam = $this->aTeam()->named('Autre équipe')->persist();

        $season = static::getContainer()->get(SeasonProvider::class)->current();
        $memberA = $this->aMember()->inTeams($teamA)->licensedFor($season)->persist();
        $this->aMember()->inTeams($otherTeam)->licensedFor($season)->persist();

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
        static::getContainer()->get(FakeBunnyStorageClient::class)->seed($storedName, "%PDF-1.4\n%%EOF\n");
        $slot->setFile($storedName, $originalName, 'application/octet-stream', 12);
        $this->em()->flush();
    }
}
