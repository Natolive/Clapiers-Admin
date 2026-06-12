<?php

namespace App\Tests\Support;

use App\Entity\AppUser;
use App\Entity\Enum\AppUserRole;
use App\Tests\Support\Builder\AppUserBuilder;
use App\Tests\Support\Builder\ContactMessageBuilder;
use App\Tests\Support\Builder\GameBuilder;
use App\Tests\Support\Builder\MemberBuilder;
use App\Tests\Support\Builder\SalleClosureBuilder;
use App\Tests\Support\Builder\TeamBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base class for API integration tests.
 *
 * Provides:
 *  - a booted KernelBrowser ($this->client)
 *  - JWT authentication helpers (actingAs*, no HTTP login round-trip)
 *  - fluent entity builders (aUser(), aTeam(), aMember(), ...)
 *  - JSON request/assertion helpers
 *
 * Database isolation is handled by DAMA\DoctrineTestBundle: every test runs
 * inside a transaction that is rolled back afterwards.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    // ── Authentication ──────────────────────────────────────────────────────

    /** Authenticate the next requests as the given user (stateless JWT header). */
    protected function actingAs(AppUser $user): AppUser
    {
        /** @var JWTTokenManagerInterface $jwtManager */
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$jwtManager->create($user));

        return $user;
    }

    protected function actingAsSuperAdmin(): AppUser
    {
        return $this->actingAs($this->aUser()->superAdmin()->persist());
    }

    protected function actingAsAdmin(): AppUser
    {
        return $this->actingAs($this->aUser()->admin()->persist());
    }

    protected function actingAsUser(): AppUser
    {
        return $this->actingAs($this->aUser()->persist());
    }

    protected function actingAsMessageViewer(): AppUser
    {
        return $this->actingAs($this->aUser()->withRole(AppUserRole::ROLE_VIEW_MESSAGE)->persist());
    }

    // ── Builders ────────────────────────────────────────────────────────────

    protected function aUser(): AppUserBuilder
    {
        return new AppUserBuilder(
            $this->em(),
            static::getContainer()->get(UserPasswordHasherInterface::class),
        );
    }

    protected function aTeam(): TeamBuilder
    {
        return new TeamBuilder($this->em());
    }

    protected function aMember(): MemberBuilder
    {
        return new MemberBuilder($this->em());
    }

    protected function aGame(): GameBuilder
    {
        return new GameBuilder($this->em());
    }

    protected function aClosure(): SalleClosureBuilder
    {
        return new SalleClosureBuilder($this->em());
    }

    protected function aContactMessage(): ContactMessageBuilder
    {
        return new ContactMessageBuilder($this->em());
    }

    // ── HTTP helpers ────────────────────────────────────────────────────────

    protected function getJson(string $uri): void
    {
        $this->requestJson('GET', $uri);
    }

    protected function postJson(string $uri, array $payload = []): void
    {
        $this->requestJson('POST', $uri, $payload);
    }

    protected function putJson(string $uri, array $payload = []): void
    {
        $this->requestJson('PUT', $uri, $payload);
    }

    protected function patchJson(string $uri, array $payload = []): void
    {
        $this->requestJson('PATCH', $uri, $payload);
    }

    protected function deleteJson(string $uri): void
    {
        $this->requestJson('DELETE', $uri);
    }

    protected function requestJson(string $method, string $uri, ?array $payload = null): void
    {
        $this->client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: $payload !== null ? json_encode($payload, JSON_THROW_ON_ERROR) : null,
        );
    }

    /** Multipart upload under the given key (defaults to "file"). */
    protected function uploadFile(string $uri, UploadedFile $file, string $key = 'file'): void
    {
        $this->client->request(
            'POST',
            $uri,
            files: [$key => $file],
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
    }

    protected function response(): Response
    {
        return $this->client->getResponse();
    }

    /**
     * Assert the response status code and return the decoded JSON body.
     * On mismatch, the body is included in the failure message to ease debugging.
     */
    protected function assertJsonResponse(int $expectedStatus): array
    {
        $response = $this->response();
        $content = (string) $response->getContent();

        $this->assertSame(
            $expectedStatus,
            $response->getStatusCode(),
            sprintf('Expected HTTP %d, got %d. Body: %s', $expectedStatus, $response->getStatusCode(), $content),
        );

        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        $this->assertIsArray($decoded, 'Response body is not valid JSON: '.$content);

        return $decoded;
    }

    // ── Fixture files ───────────────────────────────────────────────────────

    /** A small valid PDF on disk, wrapped as an UploadedFile (test mode). */
    protected function fakePdf(string $clientName = 'license.pdf'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'test_pdf_');
        file_put_contents($path, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");

        return new UploadedFile($path, $clientName, 'application/pdf', test: true);
    }

    /** A 1x1 transparent PNG on disk, wrapped as an UploadedFile (test mode). */
    protected function fakePng(string $clientName = 'photo.png'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'test_png_');
        file_put_contents($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        ));

        return new UploadedFile($path, $clientName, 'image/png', test: true);
    }

    /** A CSV file on disk, wrapped as an UploadedFile (test mode). */
    protected function fakeCsv(string $content, string $clientName = 'games.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'test_csv_');
        file_put_contents($path, $content);

        return new UploadedFile($path, $clientName, 'text/csv', test: true);
    }
}
