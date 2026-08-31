<?php

namespace App\Tests\Functional;

use App\Common\Service\MemberMediaSeeder;
use App\Common\Service\SeasonProvider;
use App\Entity\Enum\LicenseStatus;
use App\Entity\Enum\MemberStatus;
use App\Entity\Member;
use App\Repository\MemberDocumentRepository;
use App\Tests\Support\ApiTestCase;

/**
 * /api/member — la classe entière est protégée par ROLE_SUPER_ADMIN.
 */
class MemberApiTest extends ApiTestCase
{
    public function testListAllMembers(): void
    {
        $team = $this->aTeam()->persist();
        $this->aMember()->inTeams($team)->persist();
        $this->aMember()->inTeams($team)->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member');

        $body = $this->assertJsonResponse(200);
        $this->assertCount(2, $body);
    }

    public function testAdminIsForbiddenOnEveryMemberRoute(): void
    {
        // Class-level IsGranted(SUPER_ADMIN) applies even to methods that
        // declare ROLE_ADMIN (both attributes are enforced)
        $this->actingAsAdmin();

        $this->getJson('/api/member');
        $this->assertJsonResponse(403);

        $this->getJson('/api/member/1/profile-picture');
        $this->assertJsonResponse(403);
    }

    public function testUnauthenticatedIsRejected(): void
    {
        $this->getJson('/api/member');

        $this->assertJsonResponse(401);
    }

    // ── POST/PUT /api/member ────────────────────────────────────────────────

    public function testCreateMemberWithMultipleTeams(): void
    {
        $teamA = $this->aTeam()->persist();
        $teamB = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/member', $this->memberPayload([
            'teamIds' => [$teamA->getId(), $teamB->getId()],
        ]));

        $body = $this->assertJsonResponse(200);
        $this->assertSame('Lucie', $body['firstName']);
        $this->assertCount(2, $body['teams']);
        $this->assertNotEmpty($body['color'], 'A random color should be generated');

        $saved = $this->em()->getRepository(Member::class)->find($body['id']);
        $this->assertNotNull($saved);
        $this->assertCount(2, $saved->getTeams());
    }

    public function testCreateMemberWithoutTeamIsRejected(): void
    {
        $this->actingAsSuperAdmin();
        $this->postJson('/api/member', $this->memberPayload(['teamIds' => []]));

        $this->assertJsonResponse(422);
    }

    public function testCreateMemberWithInvalidEmailIsRejected(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/member', $this->memberPayload([
            'teamIds' => [$team->getId()],
            'email' => 'not-an-email',
        ]));

        $this->assertJsonResponse(422);
    }

    public function testCreateMemberWithInvalidPhoneNumberIsRejected(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/member', $this->memberPayload([
            'teamIds' => [$team->getId()],
            'phoneNumber' => '12',
        ]));

        $this->assertJsonResponse(422);
    }

    public function testCreateMemberWithUnknownTeamReturns404(): void
    {
        $this->actingAsSuperAdmin();
        $this->postJson('/api/member', $this->memberPayload(['teamIds' => [999999]]));

        $body = $this->assertJsonResponse(404);
        $this->assertSame('Team not found', $body['message']);
    }

    public function testUpdateMemberReplacesTeamsAndFields(): void
    {
        $oldTeam = $this->aTeam()->persist();
        $newTeam = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($oldTeam)->persist();

        $this->actingAsSuperAdmin();
        $this->putJson('/api/member', $this->memberPayload([
            'id' => $member->getId(),
            'firstName' => 'Renommée',
            'teamIds' => [$newTeam->getId()],
        ]));

        $body = $this->assertJsonResponse(200);
        $this->assertSame($member->getId(), $body['id']);
        $this->assertSame('Renommée', $body['firstName']);
        $this->assertCount(1, $body['teams']);
        $this->assertSame($newTeam->getId(), $body['teams'][0]['id']);
    }

    public function testUpdateUnknownMemberFails(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->putJson('/api/member', $this->memberPayload([
            'id' => 999999,
            'teamIds' => [$team->getId()],
        ]));

        $body = $this->assertJsonResponse(400);
        $this->assertSame('Member not found', $body['message']);
    }

    // ── GET /api/member/paginated ───────────────────────────────────────────

    public function testPaginatedMembersReturnsDataAndTotal(): void
    {
        $season = $this->currentSeason();
        $team = $this->aTeam()->persist();
        for ($i = 0; $i < 3; ++$i) {
            $this->aMember()->inTeams($team)->licensedFor($season)->persist();
        }

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?page=1&limit=2');

        $body = $this->assertJsonResponse(200);
        $this->assertSame(3, $body['total']);
        $this->assertCount(2, $body['data']);
    }

    public function testPaginatedMembersExcludesNonActiveMembers(): void
    {
        $active = $this->aMember()->named('Active', 'Membre')->licensedFor($this->currentSeason())->persist();

        $pending = $this->aMember()->named('Pending', 'Membre')->persist();
        $pending->setStatus(MemberStatus::PENDING_VALIDATION);
        $rejected = $this->aMember()->named('Rejected', 'Membre')->persist();
        $rejected->setStatus(MemberStatus::REJECTED);
        $this->em()->flush();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?page=1&limit=50');

        // Seuls les membres actifs apparaissent (pas les demandes en attente/refusées).
        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertSame($active->getId(), $body['data'][0]['id']);
    }

    public function testPaginatedMembersFiltersBySearch(): void
    {
        $season = $this->currentSeason();
        $team = $this->aTeam()->persist();
        $this->aMember()->named('Zoé', 'Unique')->inTeams($team)->licensedFor($season)->persist();
        $this->aMember()->named('Marc', 'Commun')->inTeams($team)->licensedFor($season)->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?search=Zoé');

        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertSame('Zoé', $body['data'][0]['firstName']);
    }

    public function testPaginatedMembersFiltersByTeam(): void
    {
        $season = $this->currentSeason();
        $teamA = $this->aTeam()->persist();
        $teamB = $this->aTeam()->persist();
        $inA = $this->aMember()->inTeams($teamA)->licensedFor($season)->persist();
        $this->aMember()->inTeams($teamB)->licensedFor($season)->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?teamId='.$teamA->getId());

        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertSame($inA->getId(), $body['data'][0]['id']);
    }

    public function testPaginatedMembersFiltersByLicensePaid(): void
    {
        $season = $this->currentSeason();
        $team = $this->aTeam()->persist();
        $paid = $this->aMember()->inTeams($team)->persist();
        $this->aLicense()->forMember($paid)->inSeason($season)->withStatus(LicenseStatus::PAYEE)->persist();
        $paid->setStatus(MemberStatus::ACTIVE); // le builder de licence l'avait passé en attente
        $this->aMember()->inTeams($team)->licensedFor($season)->persist(); // actif, licence validée mais non payée
        $this->em()->flush();

        $paidId = $paid->getId();
        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?licensePaid=true');
        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertSame($paidId, $body['data'][0]['id']);

        // Le filtre inverse ne renvoie que le membre actif sans licence payée.
        $this->getJson('/api/member/paginated?licensePaid=false');
        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertNotSame($paidId, $body['data'][0]['id']);
    }

    public function testLicensePaidFlagIsScopedToCurrentSeason(): void
    {
        $season = $this->currentSeason();
        $team = $this->aTeam()->persist();

        $current = $this->aMember()->named('Àjour', 'Saison')->inTeams($team)->persist();
        $this->aLicense()->forMember($current)->inSeason($season)->withStatus(LicenseStatus::PAYEE)->persist();
        $current->setStatus(MemberStatus::ACTIVE);

        // Licencié cette saison (validée, non payée) MAIS payé une saison passée :
        // il apparaît, et le flag « payée » ne doit voir que la saison courante.
        $past = $this->aMember()->named('Payé', 'Avant')->inTeams($team)->licensedFor($season)->persist();
        $this->aLicense()->forMember($past)->inSeason('2000-2001')->withStatus(LicenseStatus::PAYEE)->persist();
        $past->setStatus(MemberStatus::ACTIVE);
        $this->em()->flush();

        $currentId = $current->getId();
        $pastId = $past->getId();

        // Collections rechargées depuis la base : le flag reflète bien la BDD.
        $this->em()->clear();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?limit=50');
        $body = $this->assertJsonResponse(200);

        $paidById = [];
        foreach ($body['data'] as $row) {
            $paidById[$row['id']] = $row['licensePaid'];
        }
        $this->assertTrue($paidById[$currentId], 'Licence payée saison courante → à jour');
        $this->assertFalse($paidById[$pastId], 'Licence payée d’une saison passée → pas à jour');
    }

    public function testPaginatedMembersScopeToRequestedSeason(): void
    {
        $this->aMember()->named('SaisonA', 'Licencie')->licensedFor('2030-2031')->persist();
        $this->aMember()->named('SaisonB', 'Licencie')->licensedFor('2031-2032')->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?season=2030-2031&limit=50');

        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertSame('SaisonA', $body['data'][0]['firstName']);
    }

    public function testPaginatedMembersSortsByLastNameDesc(): void
    {
        $season = $this->currentSeason();
        $team = $this->aTeam()->persist();
        $this->aMember()->named('A', 'Aaa')->inTeams($team)->licensedFor($season)->persist();
        $this->aMember()->named('B', 'Zzz')->inTeams($team)->licensedFor($season)->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?sortField=lastName&sortOrder=desc');

        $body = $this->assertJsonResponse(200);
        $this->assertSame('Zzz', $body['data'][0]['lastName']);
    }

    public function testPaginatedExposesLicenseDocumentFlagFromMedia(): void
    {
        $season = static::getContainer()->get(SeasonProvider::class)->current();
        $withLicense = $this->aMember()->named('Avec', 'Licence')->licensedFor($season)->persist();
        $without = $this->aMember()->named('Sans', 'Licence')->licensedFor($season)->persist();

        // Remplit le slot Licence de la saison courante d'un seul membre.
        static::getContainer()->get(MemberMediaSeeder::class)->ensureSeason($withLicense, $season);
        $this->em()->flush();
        $slot = static::getContainer()->get(MemberDocumentRepository::class)
            ->findDefaultSlot($withLicense, $season, 'license');
        $this->assertNotNull($slot);
        $slot->setFile('x.pdf', 'x.pdf', 'application/pdf', 10);
        $this->em()->flush();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?limit=50');

        $body = $this->assertJsonResponse(200);
        $flags = [];
        foreach ($body['data'] as $row) {
            $flags[$row['id']] = $row['hasLicenseDocument'];
        }
        $this->assertTrue($flags[$withLicense->getId()], 'Slot Licence rempli → drapeau vrai');
        $this->assertFalse($flags[$without->getId()], 'Aucun document → drapeau faux');
    }

    // ── GET /api/member/team/{teamId} ───────────────────────────────────────

    public function testMembersByTeamReturnsOnlyThatTeam(): void
    {
        $season = $this->currentSeason();
        $teamA = $this->aTeam()->persist();
        $teamB = $this->aTeam()->persist();
        $inA = $this->aMember()->inTeams($teamA)->licensedFor($season)->persist();
        $inBoth = $this->aMember()->inTeams($teamA, $teamB)->licensedFor($season)->persist();
        $this->aMember()->inTeams($teamB)->licensedFor($season)->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/team/'.$teamA->getId());

        $body = $this->assertJsonResponse(200);
        $ids = array_column($body, 'id');
        sort($ids);
        $expected = [$inA->getId(), $inBoth->getId()];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function testMembersByTeamScopeToRequestedSeason(): void
    {
        $team = $this->aTeam()->persist();
        $this->aMember()->named('SaisonA', 'Equipe')->inTeams($team)->licensedFor('2030-2031')->persist();
        $this->aMember()->named('SaisonB', 'Equipe')->inTeams($team)->licensedFor('2031-2032')->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/team/'.$team->getId().'?season=2030-2031');

        $body = $this->assertJsonResponse(200);
        $this->assertCount(1, $body);
        $this->assertSame('SaisonA', $body[0]['firstName']);
    }

    public function testMembersByTeamRejectsMalformedSeason(): void
    {
        // Le format de saison est validé par la contrainte générique #[Season]
        // via MapQueryString ; une valeur invalide est rejetée (404 = statut par
        // défaut de MapQueryString en cas d'échec de validation).
        $team = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/team/'.$team->getId().'?season=not-a-season');

        $this->assertJsonResponse(404);
    }

    public function testMembersByUnknownTeamReturns404(): void
    {
        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/team/999999');

        $body = $this->assertJsonResponse(404);
        $this->assertSame('Team not found', $body['message']);
    }

    // ── Photo d'identité : lecture depuis la médiathèque ─────────────────────

    public function testProfilePictureIsServedFromMediatheque(): void
    {
        $member = $this->aMember()->persist();

        static::getContainer()->get(MemberMediaSeeder::class)->ensureRootFolders($member);
        $this->em()->flush();
        $slot = static::getContainer()->get(MemberDocumentRepository::class)
            ->findRootDocumentSlot($member, 'identity_photo');
        $this->assertNotNull($slot);

        $dir = static::getContainer()->getParameter('upload_directory').'/member-media';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir.'/pp.png', 'PNGDATA');
        $slot->setFile('pp.png', 'photo.png', 'image/png', 7);
        $this->em()->flush();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/'.$member->getId().'/profile-picture');

        $this->assertSame(200, $this->response()->getStatusCode());
    }

    public function testProfilePictureWithoutMediathequeFileReturns404(): void
    {
        $member = $this->aMember()->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/'.$member->getId().'/profile-picture');

        $this->assertJsonResponse(404);
    }

    public function testProfilePictureOnUnknownMemberReturns404(): void
    {
        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/999999/profile-picture');

        $this->assertJsonResponse(404);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function currentSeason(): string
    {
        return static::getContainer()->get(SeasonProvider::class)->current();
    }

    /**
     * Valid creation payload; override any field via $overrides.
     */
    private function memberPayload(array $overrides = []): array
    {
        return array_merge([
            'firstName' => 'Lucie',
            'lastName' => 'Bernard',
            'phoneNumber' => '+33612345678',
            'email' => 'lucie.bernard@test.fr',
            'teamIds' => [],
            'addressStreet' => '2 avenue du Gymnase',
            'addressZip' => '34830',
            'addressCity' => 'Clapiers',
            'gender' => 'female',
            'birthDate' => '2001-04-12',
            'nationality' => 'Française',
            'licenseNumber' => '123456789',
        ], $overrides);
    }
}
