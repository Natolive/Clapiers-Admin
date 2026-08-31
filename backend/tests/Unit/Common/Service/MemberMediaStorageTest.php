<?php

namespace App\Tests\Unit\Common\Service;

use App\Common\Exception\UseCaseException;
use App\Common\Service\BunnyConfigProvider;
use App\Common\Service\MemberMediaStorage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Cas d'erreur et de streaming du stockage Bunny. Le chemin nominal
 * (upload / download / delete via l'API) est couvert de bout en bout par
 * MemberMediaApiTest contre FakeBunnyStorageClient.
 */
class MemberMediaStorageTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->tempFiles = [];
    }

    public function testStoreUploadsToBunnyInTheMemberFolderAndKeepsMetadata(): void
    {
        /** @var list<array{method: string, url: string, options: array<string, mixed>}> $calls */
        $calls = [];
        $storage = $this->makeStorage($this->recorder($calls, new MockResponse('', ['http_code' => 201])));

        $meta = $storage->store($this->upload('id,name', 'liste.csv', 'text/csv'), 42);

        self::assertCount(1, $calls);
        self::assertSame('PUT', $calls[0]['method']);
        self::assertSame(
            'https://storage.bunnycdn.com/zone-test/member-media/'.$meta['storedName'],
            $calls[0]['url'],
        );
        self::assertContains('AccessKey: secret-key', $calls[0]['options']['headers']);
        self::assertSame('liste.csv', $meta['originalName']);
        self::assertSame(7, $meta['size']);
        // Un dossier par membre : « <id>/<uuid>.<ext> ».
        self::assertMatchesRegularExpression('#^42/[0-9a-f-]{36}\.[a-z]+$#', $meta['storedName']);
    }

    public function testStoreSurfacesA502WhenBunnyRejectsTheUpload(): void
    {
        $storage = $this->makeStorage(new MockHttpClient(new MockResponse('', ['http_code' => 401])));

        $this->expectException(UseCaseException::class);
        $this->expectExceptionCode(502);

        $storage->store($this->upload('x', 'doc.pdf', 'application/pdf'), 42);
    }

    public function testStoreSurfacesA502WhenBunnyIsUnreachable(): void
    {
        $storage = $this->makeStorage(new MockHttpClient(static function (): never {
            throw new TransportException('connexion refusée');
        }));

        $this->expectException(UseCaseException::class);
        $this->expectExceptionCode(502);

        $storage->store($this->upload('x', 'doc.pdf', 'application/pdf'), 42);
    }

    public function testCopyToWritesInTheTargetMemberFolderAndKeepsTheOriginal(): void
    {
        /** @var list<array{method: string, url: string, options: array<string, mixed>}> $calls */
        $calls = [];
        $storage = $this->makeStorage(new MockHttpClient(
            function (string $method, string $url, array $options) use (&$calls): MockResponse {
                $calls[] = ['method' => $method, 'url' => $url, 'options' => $options];

                return new MockResponse($method === 'GET' ? 'contenu-pdf' : '');
            }
        ));

        $stored = $storage->copyTo('17/abc.pdf', 42);

        self::assertSame('42/abc.pdf', $stored);
        // Pas de DELETE : l'original ne part qu'une fois le nouveau nom commité.
        self::assertSame(['GET', 'PUT'], array_column($calls, 'method'));
        self::assertStringEndsWith('/member-media/17/abc.pdf', $calls[0]['url']);
        self::assertStringEndsWith('/member-media/42/abc.pdf', $calls[1]['url']);
        self::assertSame('contenu-pdf', $calls[1]['options']['body']);
    }

    public function testCopyToDoesNothingWhenTheFileIsAlreadyInTheRightFolder(): void
    {
        $storage = $this->makeStorage(new MockHttpClient(function (): never {
            self::fail('Aucun appel HTTP attendu pour un fichier déjà bien rangé');
        }));

        self::assertSame('42/abc.pdf', $storage->copyTo('42/abc.pdf', 42));
    }

    public function testCopyToLeavesTheNameUntouchedWhenTheSourceIsMissing(): void
    {
        $storage = $this->makeStorage(new MockHttpClient(new MockResponse('', ['http_code' => 404])));

        self::assertSame('17/abc.pdf', $storage->copyTo('17/abc.pdf', 42));
    }

    public function testResponseStreamsTheFileWithTheMimeTypeFromDatabase(): void
    {
        $storage = $this->makeStorage(new MockHttpClient(new MockResponse(
            'contenu-pdf',
            ['response_headers' => ['content-length' => '11', 'content-type' => 'application/octet-stream']],
        )));

        $response = $storage->response('abc.pdf', 'application/pdf', 'licence.pdf');

        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertSame('11', $response->headers->get('Content-Length'));
        self::assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('licence.pdf', (string) $response->headers->get('Content-Disposition'));

        ob_start();
        $response->sendContent();
        self::assertSame('contenu-pdf', ob_get_clean());
    }

    public function testResponseReturnsNullWhenBunnyDoesNotHaveTheFile(): void
    {
        $storage = $this->makeStorage(new MockHttpClient(new MockResponse('', ['http_code' => 404])));

        self::assertNull($storage->response('missing.pdf', 'application/pdf'));
    }

    public function testResponseSurfacesA502OnBunnyError(): void
    {
        $storage = $this->makeStorage(new MockHttpClient(new MockResponse('', ['http_code' => 500])));

        $this->expectException(UseCaseException::class);
        $this->expectExceptionCode(502);

        $storage->response('abc.pdf');
    }

    public function testDeleteCallsBunnyAndToleratesAnAlreadyMissingFile(): void
    {
        /** @var list<array{method: string, url: string, options: array<string, mixed>}> $calls */
        $calls = [];
        $storage = $this->makeStorage($this->recorder($calls, new MockResponse('', ['http_code' => 404])));

        $storage->delete('abc.pdf');

        self::assertCount(1, $calls);
        self::assertSame('DELETE', $calls[0]['method']);
        self::assertSame('https://storage.bunnycdn.com/zone-test/member-media/abc.pdf', $calls[0]['url']);
    }

    public function testAnUnconfiguredZoneFailsWithoutAnyHttpCall(): void
    {
        $storage = $this->makeStorage(
            new MockHttpClient(function (): never {
                self::fail('Aucun appel HTTP attendu tant que la zone n\'est pas configurée');
            }),
            zone: '',
        );

        $this->expectException(UseCaseException::class);
        $this->expectExceptionCode(502);

        $storage->response('abc.pdf');
    }

    public function testDeleteOfNullStoredNameMakesNoCall(): void
    {
        $this->expectNotToPerformAssertions();

        $storage = $this->makeStorage(new MockHttpClient(function (): never {
            self::fail('Aucun appel HTTP attendu pour un storedName null');
        }));

        $storage->delete(null);
    }

    /**
     * @param list<array{method: string, url: string, options: array<string, mixed>}> $calls
     */
    private function recorder(array &$calls, MockResponse $response): MockHttpClient
    {
        return new MockHttpClient(
            function (string $method, string $url, array $options) use (&$calls, $response): MockResponse {
                $calls[] = ['method' => $method, 'url' => $url, 'options' => $options];

                return $response;
            }
        );
    }

    private function makeStorage(
        HttpClientInterface $httpClient,
        string $zone = 'https://storage.bunnycdn.com/zone-test',
    ): MemberMediaStorage {
        $config = $this->createStub(BunnyConfigProvider::class);
        $config->method('get')->willReturnCallback(
            static fn (string $field): string => $field === 'storageUrl' ? $zone : 'secret-key',
        );

        return new MemberMediaStorage($httpClient, new NullLogger(), $config);
    }

    private function upload(string $content, string $name, string $mimeType): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upload');
        self::assertNotFalse($path);
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return new UploadedFile($path, $name, $mimeType, test: true);
    }
}
