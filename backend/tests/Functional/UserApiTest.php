<?php

namespace App\Tests\Functional;

use App\Entity\AppUser;
use App\Entity\Enum\AppUserRole;
use App\Tests\Support\ApiTestCase;

/**
 * /api/user — profil courant + administration des comptes (super admin).
 */
class UserApiTest extends ApiTestCase
{
    public function testMeReturnsCurrentUserWithTeamsAndMember(): void
    {
        $team = $this->aTeam()->named('Mon équipe')->persist();
        $member = $this->aMember()->inTeams($team)->persist();
        $user = $this->aUser()->admin()->managing($team)->linkedTo($member)->persist();

        $this->actingAs($user);
        $this->getJson('/api/user/me');

        $body = $this->assertJsonResponse(200);
        $this->assertSame($user->getEmail(), $body['email']);
        $this->assertContains(AppUserRole::ROLE_ADMIN, $body['roles']);
        $this->assertCount(1, $body['teams']);
        $this->assertSame('Mon équipe', $body['teams'][0]['name']);
        $this->assertSame($member->getId(), $body['member']['id']);
    }

    public function testSuperAdminListsAllUsers(): void
    {
        $this->aUser()->persist();
        $this->aUser()->persist();
        $this->actingAsSuperAdmin(); // creates a third user

        $this->getJson('/api/user');

        $body = $this->assertJsonResponse(200);
        $this->assertCount(3, $body);
    }

    public function testAdminCannotListUsers(): void
    {
        $this->actingAsAdmin();
        $this->getJson('/api/user');

        $this->assertJsonResponse(403);
    }

    public function testPaginatedUsersRequireAuthentication(): void
    {
        $this->getJson('/api/user/paginated');

        $this->assertJsonResponse(401);
    }

    public function testAdminCannotListPaginatedUsers(): void
    {
        $this->actingAsAdmin();
        $this->getJson('/api/user/paginated');

        $this->assertJsonResponse(403);
    }

    public function testAdminCannotLinkOrUnlinkMembers(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->persist();
        $user = $this->aUser()->persist();

        $this->actingAsAdmin();

        $this->patchJson('/api/user/'.$user->getId().'/link-member', ['memberId' => $member->getId()]);
        $this->assertJsonResponse(403);

        $this->patchJson('/api/user/'.$user->getId().'/unlink-member');
        $this->assertJsonResponse(403);
    }

    public function testPaginatedUsersWithSearch(): void
    {
        $this->aUser()->withEmail('findme@test.fr')->persist();
        $this->aUser()->withEmail('other@test.fr')->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/user/paginated?search=findme');

        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertSame('findme@test.fr', $body['data'][0]['email']);
    }

    public function testPaginatedUsersSortByLinkedMemberName(): void
    {
        $team = $this->aTeam()->persist();
        $memberA = $this->aMember()->named('Aaa', 'Premier')->inTeams($team)->persist();
        $memberZ = $this->aMember()->named('Zzz', 'Dernier')->inTeams($team)->persist();
        $userA = $this->aUser()->withEmail('a-member@test.fr')->linkedTo($memberA)->persist();
        $this->aUser()->withEmail('z-member@test.fr')->linkedTo($memberZ)->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/user/paginated?sortField=member.name&sortOrder=asc');

        $body = $this->assertJsonResponse(200);
        // ASC puts members first (NULLS LAST), starting with the lowest first name
        $this->assertSame($userA->getEmail(), $body['data'][0]['email']);
    }

    // ── POST/PUT /api/user ──────────────────────────────────────────────────

    public function testSuperAdminCreatesUserWithRoleAndTeams(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/user', [
            'email' => 'nouveau.coach@test.fr',
            'role' => AppUserRole::ROLE_ADMIN,
            'password' => 'S3cret!pass',
            'teamIds' => [$team->getId()],
        ]);

        $body = $this->assertJsonResponse(200);
        $this->assertSame('nouveau.coach@test.fr', $body['email']);
        $this->assertContains(AppUserRole::ROLE_ADMIN, $body['roles']);
        $this->assertCount(1, $body['teams']);

        // The created user can actually log in with that password
        $this->client->setServerParameter('HTTP_AUTHORIZATION', '');
        $this->postJson('/api/login', [
            'email' => 'nouveau.coach@test.fr',
            'password' => 'S3cret!pass',
        ]);
        $this->assertJsonResponse(200);
    }

    public function testCreateUserWithoutPasswordIsRejected(): void
    {
        $this->actingAsSuperAdmin();
        $this->postJson('/api/user', [
            'email' => 'sans.mdp@test.fr',
            'role' => AppUserRole::ROLE_USER,
        ]);

        $body = $this->assertJsonResponse(400);
        $this->assertSame('Password is required for new users', $body['message']);
    }

    public function testCreateUserWithExistingEmailIsRejected(): void
    {
        $existing = $this->aUser()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/user', [
            'email' => $existing->getEmail(),
            'role' => AppUserRole::ROLE_USER,
            'password' => 'whatever',
        ]);

        $body = $this->assertJsonResponse(400);
        $this->assertSame('Email already exists', $body['message']);
    }

    public function testCreateUserWithUnknownTeamReturns404(): void
    {
        $this->actingAsSuperAdmin();
        $this->postJson('/api/user', [
            'email' => 'coach@test.fr',
            'role' => AppUserRole::ROLE_ADMIN,
            'password' => 'whatever',
            'teamIds' => [999999],
        ]);

        $body = $this->assertJsonResponse(404);
        $this->assertSame('Team 999999 not found', $body['message']);
    }

    public function testUpdateUserReplacesTeams(): void
    {
        $oldTeam = $this->aTeam()->persist();
        $newTeamA = $this->aTeam()->persist();
        $newTeamB = $this->aTeam()->persist();
        $user = $this->aUser()->admin()->managing($oldTeam)->persist();

        $this->actingAsSuperAdmin();
        $this->putJson('/api/user', [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'role' => AppUserRole::ROLE_ADMIN,
            'teamIds' => [$newTeamA->getId(), $newTeamB->getId()],
        ]);

        $body = $this->assertJsonResponse(200);
        $teamIds = array_column($body['teams'], 'id');
        sort($teamIds);
        $expected = [$newTeamA->getId(), $newTeamB->getId()];
        sort($expected);
        $this->assertSame($expected, $teamIds);
    }

    public function testUpdateUserWithNullTeamIdsKeepsExistingTeams(): void
    {
        $team = $this->aTeam()->persist();
        $user = $this->aUser()->admin()->managing($team)->persist();

        $this->actingAsSuperAdmin();
        $this->putJson('/api/user', [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'role' => AppUserRole::ROLE_ADMIN,
        ]);

        $body = $this->assertJsonResponse(200);
        $this->assertCount(1, $body['teams']);
        $this->assertSame($team->getId(), $body['teams'][0]['id']);
    }

    public function testUpdateUserCannotTakeAnotherUsersEmail(): void
    {
        $existing = $this->aUser()->withEmail('taken@test.fr')->persist();
        $user = $this->aUser()->withEmail('mine@test.fr')->persist();

        $this->actingAsSuperAdmin();
        $this->putJson('/api/user', [
            'id' => $user->getId(),
            'email' => $existing->getEmail(),
            'role' => AppUserRole::ROLE_USER,
        ]);

        $body = $this->assertJsonResponse(400);
        $this->assertSame('Email already exists', $body['message']);
    }

    public function testUpdateUserCanChangeEmailAndPassword(): void
    {
        $user = $this->aUser()->withEmail('old@test.fr')->persist();

        $this->actingAsSuperAdmin();
        $this->putJson('/api/user', [
            'id' => $user->getId(),
            'email' => 'new@test.fr',
            'role' => AppUserRole::ROLE_USER,
            'password' => 'Nouveau!mdp',
        ]);

        $body = $this->assertJsonResponse(200);
        $this->assertSame('new@test.fr', $body['email']);

        // The new credentials actually work
        $this->client->setServerParameter('HTTP_AUTHORIZATION', '');
        $this->postJson('/api/login', ['email' => 'new@test.fr', 'password' => 'Nouveau!mdp']);
        $this->assertJsonResponse(200);
    }

    public function testUpdateUnknownUserIsRejected(): void
    {
        $this->actingAsSuperAdmin();
        $this->putJson('/api/user', [
            'id' => 999999,
            'email' => 'ghost@test.fr',
            'role' => AppUserRole::ROLE_USER,
        ]);

        $body = $this->assertJsonResponse(400);
        $this->assertSame('User not found', $body['message']);
    }

    public function testAdminCannotCreateUsers(): void
    {
        $this->actingAsAdmin();
        $this->postJson('/api/user', [
            'email' => 'pirate@test.fr',
            'role' => AppUserRole::ROLE_SUPER_ADMIN,
            'password' => 'whatever',
        ]);

        $this->assertJsonResponse(403);
    }

    // ── PATCH link-member / unlink-member ───────────────────────────────────

    public function testLinkMemberToUser(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->persist();
        $user = $this->aUser()->persist();

        $this->actingAsSuperAdmin();
        $this->patchJson('/api/user/'.$user->getId().'/link-member', ['memberId' => $member->getId()]);

        $body = $this->assertJsonResponse(200);
        $this->assertSame($member->getId(), $body['member']['id']);
    }

    public function testMemberAlreadyLinkedToAnotherUserConflicts(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->persist();
        $this->aUser()->linkedTo($member)->persist();
        $secondUser = $this->aUser()->persist();

        $this->actingAsSuperAdmin();
        $this->patchJson('/api/user/'.$secondUser->getId().'/link-member', ['memberId' => $member->getId()]);

        $this->assertJsonResponse(409);
    }

    public function testRelinkingSameMemberToSameUserIsIdempotent(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->persist();
        $user = $this->aUser()->linkedTo($member)->persist();

        $this->actingAsSuperAdmin();
        $this->patchJson('/api/user/'.$user->getId().'/link-member', ['memberId' => $member->getId()]);

        $body = $this->assertJsonResponse(200);
        $this->assertSame($member->getId(), $body['member']['id']);
    }

    public function testLinkUnknownMemberReturns404(): void
    {
        $user = $this->aUser()->persist();

        $this->actingAsSuperAdmin();
        $this->patchJson('/api/user/'.$user->getId().'/link-member', ['memberId' => 999999]);

        $this->assertJsonResponse(404);
    }

    public function testLinkMemberToUnknownUserReturns404(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->persist();

        $this->actingAsSuperAdmin();
        $this->patchJson('/api/user/999999/link-member', ['memberId' => $member->getId()]);

        $this->assertJsonResponse(404);
    }

    public function testUnlinkMemberOnUnknownUserReturns404(): void
    {
        $this->actingAsSuperAdmin();
        $this->patchJson('/api/user/999999/unlink-member');

        $this->assertJsonResponse(404);
    }

    public function testUnlinkMemberFromUser(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->persist();
        $user = $this->aUser()->linkedTo($member)->persist();

        $this->actingAsSuperAdmin();
        $this->patchJson('/api/user/'.$user->getId().'/unlink-member');

        $body = $this->assertJsonResponse(200);
        $this->assertNull($body['member']);

        $this->em()->clear();
        $reloaded = $this->em()->getRepository(AppUser::class)->find($user->getId());
        $this->assertNull($reloaded->getMember());
    }
}
