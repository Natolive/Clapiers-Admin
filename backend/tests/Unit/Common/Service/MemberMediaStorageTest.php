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
        self::assertSame('https://zone-test.b-cdn.net/member-media/17/abc.pdf', $calls[0]['url']);
        self::assertSame('https://storage.bunnycdn.com/zone-test/member-media/42/abc.pdf', $calls[1]['url']);
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

    public function testAnUnconfiguredPullZoneFailsEveryReadWithoutAnyHttpCall(): void
    {
        $storage = $this->makeStorage(
            new MockHttpClient(function (): never {
                self::fail('Aucun appel HTTP attendu tant que la pull zone n\'est pas configurée');
            }),
            cdn: '',
        );

        self::assertNull($storage->signedUrl('42/abc.pdf'));

        $this->expectException(UseCaseException::class);
        $this->expectExceptionCode(502);

        $storage->contents('42/abc.pdf');
    }

    public function testReadsGoThroughTheCdnWithASignedUrlWhenAPullZoneIsConfigured(): void
    {
        /** @var list<array{method: string, url: string, options: array<string, mixed>}> $calls */
        $calls = [];
        $storage = $this->makeStorage(
            $this->recorder($calls, new MockResponse('contenu-pdf')),
            tokenKey: 'cle-token',
        );

        self::assertSame('contenu-pdf', $storage->contents('42/abc.pdf'));

        self::assertCount(1, $calls);
        // Pas d'AccessKey vers le CDN : c'est le token qui fait foi.
        self::assertNotContains('AccessKey: secret-key', $calls[0]['options']['headers']);

        $query = [];
        parse_str((string) parse_url($calls[0]['url'], PHP_URL_QUERY), $query);
        self::assertSame(
            'https://zone-test.b-cdn.net/member-media/42/abc.pdf',
            strtok($calls[0]['url'], '?'),
        );
        self::assertGreaterThan(time(), (int) $query['expires']);
        // Signature Bunny : md5(clé + chemin + expiration), base64 url-safe non bourré.
        self::assertSame(
            rtrim(strtr(base64_encode(md5('cle-token/member-media/42/abc.pdf'.$query['expires'], true)), '+/', '-_'), '='),
            $query['token'],
        );
    }

    public function testAnUnconfiguredStorageZoneFailsEveryWrite(): void
    {
        $storage = $this->makeStorage(
            new MockHttpClient(function (): never {
                self::fail('Aucun appel HTTP attendu tant que la zone n\'est pas configurée');
            }),
            zone: '',
        );

        $this->expectException(UseCaseException::class);
        $this->expectExceptionCode(502);

        $storage->delete('42/abc.pdf');
    }

    /**
     * Le cache navigateur — et celui du CDN — dépendent d'une URL stable : deux
     * rendus de la même liste ne doivent pas produire deux URL différentes,
     * sinon chaque avatar est retéléchargé à chaque écran.
     */
    public function testTheSignedUrlIsStableBetweenTwoCloseCalls(): void
    {
        $storage = $this->makeStorage(new MockHttpClient(), tokenKey: 'cle-token');

        $first = $storage->signedUrl('42/abc.pdf', MemberMediaStorage::DISPLAY_TTL);
        self::assertSame($first, $storage->signedUrl('42/abc.pdf', MemberMediaStorage::DISPLAY_TTL));

        // …et reste valable au moins le TTL demandé.
        parse_str((string) parse_url((string) $first, PHP_URL_QUERY), $query);
        self::assertGreaterThanOrEqual(time() + MemberMediaStorage::DISPLAY_TTL, (int) $query['expires']);
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
        string $cdn = 'https://zone-test.b-cdn.net',
        string $tokenKey = '',
    ): MemberMediaStorage {
        $values = [
            'storageUrl' => $zone,
            'storageKey' => 'secret-key',
            'cdnUrl' => $cdn,
            'tokenKey' => $tokenKey,
        ];

        $config = $this->createStub(BunnyConfigProvider::class);
        $config->method('get')->willReturnCallback(static fn (string $field): string => $values[$field]);

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
