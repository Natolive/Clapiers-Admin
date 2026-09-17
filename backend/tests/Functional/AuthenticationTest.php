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

    /**
     * La session dure 7 jours. Valeur volontairement longue — l'auth est
     * stateless, sans refresh token ni révocation, donc ce TTL est à la fois la
     * durée de confort et la fenêtre pendant laquelle un jeton volé reste
     * valable. Ce test est là pour qu'elle ne bouge pas par accident, et pour
     * que la faire bouger soit un choix explicite (le cookie du front,
     * `TOKEN_MAX_AGE`, doit suivre).
     */
    public function testIssuedTokenLastsSevenDays(): void
    {
        $user = $this->aUser()->persist();

        $this->postJson('/api/login', [
            'email' => $user->getEmail(),
            'password' => AppUserBuilder::DEFAULT_PASSWORD,
        ]);

        $payload = $this->jwtPayload($this->assertJsonResponse(200)['token']);

        $this->assertSame(7 * 24 * 3600, $payload['exp'] - $payload['iat']);
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

    /**
     * Le corps d'un JWT : trois parties séparées par des points, la deuxième
     * étant du JSON en base64url (alphabet -_ et bourrage retiré).
     *
     * @return array<string, mixed>
     */
    private function jwtPayload(string $token): array
    {
        $parts = explode('.', $token);
        $this->assertCount(3, $parts, 'Un JWT a trois segments');

        $json = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $this->assertNotFalse($json);

        $payload = json_decode((string) $json, true);
        $this->assertIsArray($payload);

        return $payload;
    }

    public function testJwtIssuedByTestHelperGrantsAccess(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/user/me');

        $body = $this->assertJsonResponse(200);
        $this->assertArrayHasKey('email', $body);
    }
}
