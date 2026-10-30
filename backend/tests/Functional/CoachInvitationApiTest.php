<?php

namespace App\Tests\Functional;

use App\Entity\AppUser;
use App\Entity\Enum\AppUserRole;
use App\Common\Service\SeasonProvider;
use App\Tests\Support\ApiTestCase;

/**
 * Invitation d'un licencié comme chef d'équipe : POST /api/user/invite (super
 * admin) puis lien public /api/public/invitation/{token} pour choisir son mot
 * de passe.
 */
class CoachInvitationApiTest extends ApiTestCase
{
    private function invitedUser(int $memberId): AppUser
    {
        $this->em()->clear();

        return $this->em()->getRepository(AppUser::class)->findOneBy(['member' => $memberId]);
    }

    public function testSuperAdminInvitesAMemberAsTeamManager(): void
    {
        $teamA = $this->aTeam()->named('Seniors A')->persist();
        $teamB = $this->aTeam()->named('Seniors B')->persist();
        $member = $this->aMember()->withEmail('coach@test.fr')->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/user/invite', ['memberId' => $member->getId(), 'teamIds' => [$teamA->getId(), $teamB->getId()]]);

        $body = $this->assertJsonResponse(200);
        $this->assertSame('coach@test.fr', $body['email']);
        $this->assertContains(AppUserRole::ROLE_COACH, $body['roles']);
        $this->assertCount(2, $body['teams']);
        $this->assertSame($member->getId(), $body['member']['id']);
        $this->assertSame('invited', $body['invitationStatus']);

        $user = $this->invitedUser($member->getId());
        $this->assertEmailCount(1);
        $this->assertEmailHtmlBodyContains($this->getMailerMessage(), '/invitation#'.$user->getInvitationToken());
        $this->assertEmailHtmlBodyContains($this->getMailerMessage(), 'Seniors A, Seniors B');
    }

    public function testInvitedUserCannotLogInBeforeChoosingAPassword(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->withEmail('coach@test.fr')->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/user/invite', ['memberId' => $member->getId(), 'teamIds' => [$team->getId()]]);
        $this->assertJsonResponse(200);

        $this->postJson('/api/login', ['email' => 'coach@test.fr', 'password' => '']);
        $this->assertNotSame(200, $this->client->getResponse()->getStatusCode());
    }

    public function testInviteRequiresAuthentication(): void
    {
        $this->postJson('/api/user/invite', ['memberId' => 1, 'teamIds' => [1]]);

        $this->assertJsonResponse(401);
    }

    public function testCoachCannotInvite(): void
    {
        $this->actingAsCoach();
        $this->postJson('/api/user/invite', ['memberId' => 1, 'teamIds' => [1]]);

        $this->assertJsonResponse(403);
    }

    public function testInviteWithoutTeamIsRejected(): void
    {
        $member = $this->aMember()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/user/invite', ['memberId' => $member->getId(), 'teamIds' => []]);

        $this->assertJsonResponse(422);
        $this->assertEmailCount(0);
    }

    public function testInviteUnknownMemberOrTeamIs404(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/user/invite', ['memberId' => 999999, 'teamIds' => [$team->getId()]]);
        $this->assertJsonResponse(404);

        $this->postJson('/api/user/invite', ['memberId' => $member->getId(), 'teamIds' => [999999]]);
        $this->assertJsonResponse(404);
        $this->assertEmailCount(0);
    }

    public function testEmailTakenByAnotherAccountIsAConflict(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->withEmail('pris@test.fr')->persist();
        $this->aUser()->withEmail('pris@test.fr')->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/user/invite', ['memberId' => $member->getId(), 'teamIds' => [$team->getId()]]);

        $this->assertJsonResponse(409);
    }

    public function testMemberWithoutEmailCannotBeInvited(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->withEmail('')->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/user/invite', ['memberId' => $member->getId(), 'teamIds' => [$team->getId()]]);

        $this->assertJsonResponse(422);
    }

    /** Relancer : nouveau lien (l'ancien meurt), équipes remplacées, super admin jamais rétrogradé. */
    public function testReinvitingRenewsTheLinkAndReplacesTeams(): void
    {
        $old = $this->aTeam()->persist();
        $new = $this->aTeam()->named('Nouvelle')->persist();
        $member = $this->aMember()->persist();
        $user = $this->aUser()->superAdmin()->managing($old)->linkedTo($member)->persist();
        $user->renewInvitation();
        $this->em()->flush();
        $oldToken = $user->getInvitationToken();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/user/invite', ['memberId' => $member->getId(), 'teamIds' => [$new->getId()]]);

        $body = $this->assertJsonResponse(200);
        $this->assertSame($user->getId(), $body['id']);
        $this->assertContains(AppUserRole::ROLE_SUPER_ADMIN, $body['roles']);
        $this->assertSame(['Nouvelle'], array_column($body['teams'], 'name'));

        $this->getJson('/api/public/invitation/'.$oldToken);
        $this->assertJsonResponse(404);
    }

    // ── Lien public ─────────────────────────────────────────────────────────

    public function testInviteeChoosesPasswordThenLogsIn(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->named('Léa', 'Martin')->withEmail('lea@test.fr')->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/user/invite', ['memberId' => $member->getId(), 'teamIds' => [$team->getId()]]);
        $this->assertJsonResponse(200);
        $token = $this->invitedUser($member->getId())->getInvitationToken();

        $this->getJson('/api/public/invitation/'.$token);
        $this->assertSame(['email' => 'lea@test.fr', 'firstName' => 'Léa'], $this->assertJsonResponse(200));

        $this->postJson('/api/public/invitation/'.$token, ['password' => 'Nouveau-mdp1']);
        $this->assertJsonResponse(200);
        $this->assertNull($this->invitedUser($member->getId())->getInvitationToken());

        $this->postJson('/api/login', ['email' => 'lea@test.fr', 'password' => 'Nouveau-mdp1']);
        $this->assertArrayHasKey('token', $this->assertJsonResponse(200));

        // Lien à usage unique.
        $this->postJson('/api/public/invitation/'.$token, ['password' => 'Autre-mdp12']);
        $this->assertJsonResponse(404);
    }

    public function testTooShortPasswordIsRejected(): void
    {
        $member = $this->aMember()->persist();
        $user = $this->aUser()->linkedTo($member)->persist();
        $user->renewInvitation();
        $this->em()->flush();

        $this->postJson('/api/public/invitation/'.$user->getInvitationToken(), ['password' => 'court']);

        $this->assertJsonResponse(422);
    }

    public function testExpiredInvitationIsGone(): void
    {
        $user = $this->aUser()->persist();
        $user->renewInvitation()->setInvitationExpiresAt(new \DateTimeImmutable('-1 day'));
        $this->em()->flush();

        $this->getJson('/api/public/invitation/'.$user->getInvitationToken());
        $this->assertJsonResponse(410);

        $this->postJson('/api/public/invitation/'.$user->getInvitationToken(), ['password' => 'Nouveau-mdp1']);
        $this->assertJsonResponse(410);
    }

    /** La liste des licenciés montre où en est chaque invitation (badge + relance). */
    public function testMemberListShowsInvitationStatus(): void
    {
        $season = static::getContainer()->get(SeasonProvider::class)->current();
        $team = $this->aTeam()->persist();
        $invited = $this->aMember()->named('Aaa', 'Invité')->licensedFor($season)->persist();
        $expired = $this->aMember()->named('Bbb', 'Expiré')->licensedFor($season)->persist();
        $active = $this->aMember()->named('Ccc', 'Actif')->licensedFor($season)->persist();
        $this->aMember()->named('Ddd', 'Sans compte')->licensedFor($season)->persist();

        $this->aUser()->linkedTo($invited)->managing($team)->persist()->renewInvitation();
        $this->aUser()->linkedTo($expired)->persist()->renewInvitation()->setInvitationExpiresAt(new \DateTimeImmutable('-1 day'));
        $this->aUser()->linkedTo($active)->persist();
        $this->em()->flush();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?page=1&limit=50');
        $accounts = array_column($this->assertJsonResponse(200)['data'], 'account', 'lastName');

        $this->assertSame(['invitationStatus' => 'invited', 'teamIds' => [$team->getId()]], $accounts['Invité']);
        $this->assertSame('expired', $accounts['Expiré']['invitationStatus']);
        $this->assertSame('active', $accounts['Actif']['invitationStatus']);
        $this->assertNull($accounts['Sans compte']);
    }

    public function testUnknownInvitationIs404(): void
    {
        $this->getJson('/api/public/invitation/'.str_repeat('a', 64));

        $this->assertJsonResponse(404);
    }
}
