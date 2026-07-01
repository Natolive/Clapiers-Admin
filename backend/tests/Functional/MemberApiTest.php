<?php

namespace App\Tests\Functional;

use App\Entity\Enum\LicenseStatus;
use App\Entity\Enum\MemberStatus;
use App\Entity\Member;
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
        $team = $this->aTeam()->persist();
        for ($i = 0; $i < 3; ++$i) {
            $this->aMember()->inTeams($team)->persist();
        }

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?page=1&limit=2');

        $body = $this->assertJsonResponse(200);
        $this->assertSame(3, $body['total']);
        $this->assertCount(2, $body['data']);
    }

    public function testPaginatedMembersExcludesNonActiveMembers(): void
    {
        $active = $this->aMember()->named('Active', 'Membre')->persist();

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
        $team = $this->aTeam()->persist();
        $this->aMember()->named('Zoé', 'Unique')->inTeams($team)->persist();
        $this->aMember()->named('Marc', 'Commun')->inTeams($team)->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?search=Zoé');

        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertSame('Zoé', $body['data'][0]['firstName']);
    }

    public function testPaginatedMembersFiltersByTeam(): void
    {
        $teamA = $this->aTeam()->persist();
        $teamB = $this->aTeam()->persist();
        $inA = $this->aMember()->inTeams($teamA)->persist();
        $this->aMember()->inTeams($teamB)->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?teamId='.$teamA->getId());

        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertSame($inA->getId(), $body['data'][0]['id']);
    }

    public function testPaginatedMembersFiltersByLicensePaid(): void
    {
        $team = $this->aTeam()->persist();
        $paid = $this->aMember()->inTeams($team)->persist();
        $this->aLicense()->forMember($paid)->withStatus(LicenseStatus::PAYEE)->persist();
        $paid->setStatus(MemberStatus::ACTIVE); // le builder de licence l'avait passé en attente
        $this->aMember()->inTeams($team)->persist(); // actif, sans licence payée
        $this->em()->flush();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?licensePaid=true');
        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertSame($paid->getId(), $body['data'][0]['id']);

        // Le filtre inverse ne renvoie que le membre actif sans licence payée.
        $this->getJson('/api/member/paginated?licensePaid=false');
        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertNotSame($paid->getId(), $body['data'][0]['id']);
    }

    public function testPaginatedMembersFiltersByHasLicense(): void
    {
        $team = $this->aTeam()->persist();
        $withFile = $this->aMember()->inTeams($team)->withLicenseFileName('doc.pdf')->persist();
        $without = $this->aMember()->inTeams($team)->persist();

        $this->actingAsSuperAdmin();

        $this->getJson('/api/member/paginated?hasLicense=true');
        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertSame($withFile->getId(), $body['data'][0]['id']);

        $this->getJson('/api/member/paginated?hasLicense=false');
        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertSame($without->getId(), $body['data'][0]['id']);
    }

    public function testPaginatedMembersSortsByLastNameDesc(): void
    {
        $team = $this->aTeam()->persist();
        $this->aMember()->named('A', 'Aaa')->inTeams($team)->persist();
        $this->aMember()->named('B', 'Zzz')->inTeams($team)->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?sortField=lastName&sortOrder=desc');

        $body = $this->assertJsonResponse(200);
        $this->assertSame('Zzz', $body['data'][0]['lastName']);
    }

    // ── GET /api/member/team/{teamId} ───────────────────────────────────────

    public function testMembersByTeamReturnsOnlyThatTeam(): void
    {
        $teamA = $this->aTeam()->persist();
        $teamB = $this->aTeam()->persist();
        $inA = $this->aMember()->inTeams($teamA)->persist();
        $inBoth = $this->aMember()->inTeams($teamA, $teamB)->persist();
        $this->aMember()->inTeams($teamB)->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/team/'.$teamA->getId());

        $body = $this->assertJsonResponse(200);
        $ids = array_column($body, 'id');
        sort($ids);
        $expected = [$inA->getId(), $inBoth->getId()];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function testMembersByUnknownTeamReturns404(): void
    {
        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/team/999999');

        $body = $this->assertJsonResponse(404);
        $this->assertSame('Team not found', $body['message']);
    }

    // ── Licence : upload / download / delete ────────────────────────────────

    public function testLicenseUploadDownloadDeleteLifecycle(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->persist();
        $this->actingAsSuperAdmin();

        // Upload
        $this->uploadFile('/api/member/'.$member->getId().'/upload-license', $this->fakePdf());
        $body = $this->assertJsonResponse(200);
        $this->assertNotNull($body['licenseFileName']);

        // Download
        $this->getJson('/api/member/'.$member->getId().'/download-license');
        $this->assertSame(200, $this->response()->getStatusCode());

        // Delete
        $this->deleteJson('/api/member/'.$member->getId().'/delete-license');
        $body = $this->assertJsonResponse(200);
        $this->assertNull($body['licenseFileName']);

        // Download after delete
        $this->getJson('/api/member/'.$member->getId().'/download-license');
        $this->assertJsonResponse(404);
    }

    public function testReuploadingLicenseReplacesTheOldFile(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->persist();
        $this->actingAsSuperAdmin();

        $this->uploadFile('/api/member/'.$member->getId().'/upload-license', $this->fakePdf());
        $firstFileName = $this->assertJsonResponse(200)['licenseFileName'];

        $this->uploadFile('/api/member/'.$member->getId().'/upload-license', $this->fakePdf('new.pdf'));
        $secondFileName = $this->assertJsonResponse(200)['licenseFileName'];

        $this->assertNotSame($firstFileName, $secondFileName);

        $licensesDir = static::getContainer()->getParameter('upload_directory').'/licenses';
        $this->assertFileDoesNotExist($licensesDir.'/'.$firstFileName, 'The replaced file must be deleted from disk');
        $this->assertFileExists($licensesDir.'/'.$secondFileName);
    }

    public function testReuploadingProfilePictureReplacesTheOldFile(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->persist();
        $this->actingAsSuperAdmin();

        $this->uploadFile('/api/member/'.$member->getId().'/upload-profile-picture', $this->fakePng());
        $firstFileName = $this->assertJsonResponse(200)['profilePicture'];

        $this->uploadFile('/api/member/'.$member->getId().'/upload-profile-picture', $this->fakePng('new.png'));
        $secondFileName = $this->assertJsonResponse(200)['profilePicture'];

        $this->assertNotSame($firstFileName, $secondFileName);

        $picturesDir = static::getContainer()->getParameter('upload_directory').'/profile-pictures';
        $this->assertFileDoesNotExist($picturesDir.'/'.$firstFileName, 'The replaced file must be deleted from disk');
        $this->assertFileExists($picturesDir.'/'.$secondFileName);
    }

    public function testUploadLicenseOnUnknownMemberFails(): void
    {
        $this->actingAsSuperAdmin();
        $this->uploadFile('/api/member/999999/upload-license', $this->fakePdf());

        $this->assertJsonResponse(400);
    }

    public function testFileRoutesOnUnknownMemberFail(): void
    {
        $this->actingAsSuperAdmin();

        $this->uploadFile('/api/member/999999/upload-profile-picture', $this->fakePng());
        $this->assertJsonResponse(400);

        $this->deleteJson('/api/member/999999/delete-license');
        $this->assertJsonResponse(400);

        $this->deleteJson('/api/member/999999/delete-profile-picture');
        $this->assertJsonResponse(400);
    }

    public function testDownloadLicenseWithoutFileReturns404(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/'.$member->getId().'/download-license');

        $this->assertJsonResponse(404);
    }

    public function testDownloadLicenseMissingOnDiskReturns404(): void
    {
        $team = $this->aTeam()->persist();
        // DB row references a file that does not exist on disk
        $member = $this->aMember()->inTeams($team)->withLicenseFileName('ghost.pdf')->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/'.$member->getId().'/download-license');

        $this->assertJsonResponse(404);
    }

    public function testProfilePictureMissingOnDiskReturns404(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->withProfilePicture('ghost.png')->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/'.$member->getId().'/profile-picture');

        $this->assertJsonResponse(404);
    }

    public function testDeletingAbsentLicenseAndPictureIsANoOp(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->persist();

        $this->actingAsSuperAdmin();

        $this->deleteJson('/api/member/'.$member->getId().'/delete-license');
        $this->assertJsonResponse(200);

        $this->deleteJson('/api/member/'.$member->getId().'/delete-profile-picture');
        $this->assertJsonResponse(200);
    }

    public function testDeletingFilesMissingOnDiskStillClearsTheDatabase(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)
            ->withLicenseFileName('ghost.pdf')
            ->withProfilePicture('ghost.png')
            ->persist();

        $this->actingAsSuperAdmin();

        $this->deleteJson('/api/member/'.$member->getId().'/delete-license');
        $this->assertNull($this->assertJsonResponse(200)['licenseFileName']);

        $this->deleteJson('/api/member/'.$member->getId().'/delete-profile-picture');
        $this->assertNull($this->assertJsonResponse(200)['profilePicture']);
    }

    public function testUploadingOverAFileMissingOnDiskStillWorks(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->withProfilePicture('ghost.png')->persist();

        $this->actingAsSuperAdmin();
        $this->uploadFile('/api/member/'.$member->getId().'/upload-profile-picture', $this->fakePng());

        $body = $this->assertJsonResponse(200);
        $this->assertNotSame('ghost.png', $body['profilePicture']);
    }

    // ── Photo de profil : upload / get / delete ─────────────────────────────

    public function testProfilePictureUploadGetDeleteLifecycle(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->persist();
        $this->actingAsSuperAdmin();

        $this->uploadFile('/api/member/'.$member->getId().'/upload-profile-picture', $this->fakePng());
        $body = $this->assertJsonResponse(200);
        $this->assertNotNull($body['profilePicture']);

        $this->getJson('/api/member/'.$member->getId().'/profile-picture');
        $this->assertSame(200, $this->response()->getStatusCode());

        $this->deleteJson('/api/member/'.$member->getId().'/delete-profile-picture');
        $body = $this->assertJsonResponse(200);
        $this->assertNull($body['profilePicture']);

        $this->getJson('/api/member/'.$member->getId().'/profile-picture');
        $this->assertJsonResponse(404);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

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
