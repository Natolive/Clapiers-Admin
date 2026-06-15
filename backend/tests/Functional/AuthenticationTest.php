<?php

namespace App\Tests\Functional;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Builder\AppUserBuilder;

class AuthenticationTest extends ApiTestCase
{
    public function testLoginWithValidCredentialsReturnsJwtToken(): void
    {
        $user = $this->aUser()->withEmail('coach@test.fr')->persist();

        $this->postJson('/api/login', [
            'email' => $user->getEmail(),
            'password' => AppUserBuilder::DEFAULT_PASSWORD,
        ]);

        $body = $this->assertJsonResponse(200);
        $this->assertArrayHasKey('token', $body);
        $this->assertNotEmpty($body['token']);
    }

    public function testLoginWithWrongPasswordIsRejected(): void
    {
        $user = $this->aUser()->persist();

        $this->postJson('/api/login', [
            'email' => $user->getEmail(),
            'password' => 'wrong-password',
        ]);

        $this->assertJsonResponse(401);
    }

    public function testLoginWithUnknownEmailIsRejected(): void
    {
        $this->postJson('/api/login', [
            'email' => 'nobody@test.fr',
            'password' => 'whatever',
        ]);

        $this->assertJsonResponse(401);
    }

    public function testLoginRehashesOutdatedPasswordHash(): void
    {
        // Hash with a different bcrypt cost than the test config (4): the
        // security system migrates it on login via UserRepository::upgradePassword()
        $user = $this->aUser()->withEmail('legacy@test.fr')->build();
        $user->setPassword(password_hash('password', PASSWORD_BCRYPT, ['cost' => 5]));
        $this->em()->persist($user);
        $this->em()->flush();
        $oldHash = $user->getPassword();

        $this->postJson('/api/login', ['email' => 'legacy@test.fr', 'password' => 'password']);

        $this->assertJsonResponse(200);
        $this->em()->refresh($user);
        $this->assertNotSame($oldHash, $user->getPassword(), 'The hash should have been upgraded');
    }

    public function testProtectedRouteWithoutTokenIsRejected(): void
    {
        $this->getJson('/api/user/me');

        $this->assertJsonResponse(401);
    }

    public function testProtectedRouteWithInvalidTokenIsRejected(): void
    {
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer not-a-real-token');
        $this->getJson('/api/user/me');

        $this->assertJsonResponse(401);
    }

    public function testJwtIssuedByTestHelperGrantsAccess(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/user/me');

        $body = $this->assertJsonResponse(200);
        $this->assertArrayHasKey('email', $body);
    }
}
