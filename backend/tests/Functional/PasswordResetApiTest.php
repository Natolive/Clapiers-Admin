<?php

namespace App\Tests\Functional;

use App\Application\UseCase\User\RequestPasswordReset\RequestPasswordResetUseCase;
use App\Entity\AppUser;
use App\Tests\Support\ApiTestCase;

/**
 * « Mot de passe oublié » : POST /api/public/password-reset puis lien public
 * /api/public/password-reset/{token}. Jamais pour une invitation en attente.
 */
class PasswordResetApiTest extends ApiTestCase
{
    private function reload(AppUser $user): AppUser
    {
        $this->em()->clear();

        return $this->em()->getRepository(AppUser::class)->find($user->getId());
    }

    private function requestReset(string $email): void
    {
        $this->postJson('/api/public/password-reset', ['email' => $email]);
        $this->assertSame(['message' => RequestPasswordResetUseCase::MESSAGE], $this->assertJsonResponse(200));
    }

    public function testActiveUserResetsPasswordThenLogsIn(): void
    {
        $user = $this->aUser()->withEmail('coach@test.fr')->persist();

        $this->requestReset('coach@test.fr');
        $token = $this->reload($user)->getPasswordResetToken();
        $this->assertNotNull($token);
        $this->assertEmailCount(1);
        $this->assertEmailHtmlBodyContains($this->getMailerMessage(), '/mot-de-passe#'.$token);

        $this->getJson('/api/public/password-reset/'.$token);
        $this->assertSame(['email' => 'coach@test.fr'], $this->assertJsonResponse(200));

        $this->postJson('/api/public/password-reset/'.$token, ['password' => 'Nouveau-mdp1']);
        $this->assertJsonResponse(200);

        // Sessions ouvertes révoquées (cf. AuthenticationTest pour l'effet sur un JWT).
        $this->assertNotNull($this->reload($user)->getPasswordChangedAt());

        $this->postJson('/api/login', ['email' => 'coach@test.fr', 'password' => 'Nouveau-mdp1']);
        $this->assertArrayHasKey('token', $this->assertJsonResponse(200));
        $this->postJson('/api/login', ['email' => 'coach@test.fr', 'password' => 'password']);
        $this->assertJsonResponse(401);

        // Lien à usage unique.
        $this->postJson('/api/public/password-reset/'.$token, ['password' => 'Autre-mdp12']);
        $this->assertJsonResponse(404);
    }

    /** Même réponse qu'un compte existant : on ne révèle pas qui est inscrit. */
    public function testUnknownEmailGetsTheSameAnswerAndNoMail(): void
    {
        $this->requestReset('personne@test.fr');

        $this->assertEmailCount(0);
    }

    /**
     * Un compte invité n'a jamais choisi de mot de passe : c'est l'invitation qui
     * l'active, le reset ne doit pas la contourner (en cours comme expirée).
     */
    public function testPendingOrExpiredInvitationBlocksTheReset(): void
    {
        $invited = $this->aUser()->withEmail('invite@test.fr')->persist()->renewInvitation();
        $expired = $this->aUser()->withEmail('expire@test.fr')->persist()->renewInvitation()
            ->setInvitationExpiresAt(new \DateTimeImmutable('-1 day'));
        $this->em()->flush();

        $this->requestReset('invite@test.fr');
        $this->requestReset('expire@test.fr');

        $this->assertEmailCount(0);
        $this->assertNull($this->reload($invited)->getPasswordResetToken());
        $this->assertNull($this->reload($expired)->getPasswordResetToken());
    }

    /** Une invitation (re)lancée après la demande tue le lien de reset. */
    public function testInvitationSentAfterTheRequestKillsTheResetLink(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->withEmail('coach@test.fr')->persist();
        $user = $this->aUser()->withEmail('coach@test.fr')->linkedTo($member)->persist();

        $this->requestReset('coach@test.fr');
        $token = $this->reload($user)->getPasswordResetToken();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/user/invite', ['memberId' => $member->getId(), 'teamIds' => [$team->getId()]]);
        $this->assertJsonResponse(200);

        $this->postJson('/api/public/password-reset/'.$token, ['password' => 'Nouveau-mdp1']);
        $this->assertJsonResponse(404);
    }

    /** Anti-spam : pas de second mail dans les 5 minutes, le premier lien reste bon. */
    public function testRepeatedRequestsWithinTheCooldownSendOneMail(): void
    {
        $user = $this->aUser()->withEmail('coach@test.fr')->persist();

        $this->requestReset('coach@test.fr');
        $this->assertEmailCount(1);
        $token = $this->reload($user)->getPasswordResetToken();

        $this->requestReset('coach@test.fr');
        $this->assertEmailCount(0, 'La seconde demande ne renvoie pas de mail');
        $this->assertSame($token, $this->reload($user)->getPasswordResetToken());
    }

    public function testExpiredResetLinkIsGone(): void
    {
        $user = $this->aUser()->persist()->renewPasswordReset()
            ->setPasswordResetExpiresAt(new \DateTimeImmutable('-1 minute'));
        $this->em()->flush();

        $this->getJson('/api/public/password-reset/'.$user->getPasswordResetToken());
        $this->assertJsonResponse(410);

        $this->postJson('/api/public/password-reset/'.$user->getPasswordResetToken(), ['password' => 'Nouveau-mdp1']);
        $this->assertJsonResponse(410);
    }

    public function testTooShortPasswordIsRejected(): void
    {
        $user = $this->aUser()->persist()->renewPasswordReset();
        $this->em()->flush();

        $this->postJson('/api/public/password-reset/'.$user->getPasswordResetToken(), ['password' => 'court']);

        $this->assertJsonResponse(422);
    }

    /** Au-delà de 4096 caractères le hasher plantait (500). */
    public function testOverlongPasswordIsRejected(): void
    {
        $user = $this->aUser()->persist()->renewPasswordReset();
        $this->em()->flush();

        $this->postJson('/api/public/password-reset/'.$user->getPasswordResetToken(), ['password' => str_repeat('a', 4097)]);

        $this->assertJsonResponse(422);
    }

    public function testInvalidEmailIsRejected(): void
    {
        $this->postJson('/api/public/password-reset', ['email' => 'pas-un-email']);

        $this->assertJsonResponse(422);
    }

    public function testUnknownResetLinkIs404(): void
    {
        $this->getJson('/api/public/password-reset/'.str_repeat('b', 64));

        $this->assertJsonResponse(404);
    }
}
