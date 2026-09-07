<?php

namespace App\Tests\Support\Fake;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Zone Bunny Storage en mémoire. Les tests exercent le vrai
 * MemberMediaStorage (PUT / GET / DELETE, streaming) sans réseau ni disque.
 *
 * Le contenu est statique parce que le KernelBrowser reboote le noyau entre
 * deux requêtes : une instance par requête perdrait le fichier uploadé juste
 * avant. ApiTestCase::setUp() vide la zone à chaque test.
 */
class FakeBunnyStorageClient extends MockHttpClient
{
    /** @var array<string, string> nom stocké → contenu */
    private static array $files = [];

    private static int $putCount = 0;

    /** Rang du premier PUT à faire échouer (test des chemins d'erreur). */
    private static ?int $failPutFrom = null;

    private static bool $failDeletes = false;

    public function __construct()
    {
        parent::__construct(function (string $method, string $url, array $options): MockResponse {
            // Clé = chemin relatif dans la zone (« <id membre>/<uuid>.<ext> »),
            // exactement ce que le service enregistre en `storedName`.
            $name = ltrim((string) strstr((string) parse_url($url, PHP_URL_PATH), '/member-media/'), '/');
            $name = substr($name, \strlen('member-media/'));

            return match ($method) {
                'PUT' => $this->handlePut($name, $options),
                'DELETE' => $this->handleDelete($name),
                default => $this->handleGet($name),
            };
        });
    }

    public static function clear(): void
    {
        self::$files = [];
        self::$putCount = 0;
        self::$failPutFrom = null;
        self::$failDeletes = false;
    }

    /** Fait répondre 500 à partir du n-ième PUT du test (1 = le premier). */
    public static function failPutsFromCall(int $nth): void
    {
        self::$failPutFrom = $nth;
    }

    /** Fait échouer toute suppression (ménage impossible côté zone). */
    public static function failDeletes(): void
    {
        self::$failDeletes = true;
    }

    /** Pré-remplit la zone, pour les tests qui posent un fichier sans passer par l'API. */
    public function seed(string $storedName, string $content): void
    {
        self::$files[$storedName] = $content;
    }

    public function has(string $storedName): bool
    {
        return isset(self::$files[$storedName]);
    }

    /** @param array<string, mixed> $options */
    private function handlePut(string $name, array $options): MockResponse
    {
        ++self::$putCount;
        if (self::$failPutFrom !== null && self::$putCount >= self::$failPutFrom) {
            return new MockResponse('', ['http_code' => 500]);
        }

        self::$files[$name] = $this->readBody($options['body'] ?? '');

        return new MockResponse('', ['http_code' => 201]);
    }

    private function handleDelete(string $name): MockResponse
    {
        if (self::$failDeletes) {
            return new MockResponse('', ['http_code' => 500]);
        }

        if (!isset(self::$files[$name])) {
            return new MockResponse('', ['http_code' => 404]);
        }

        unset(self::$files[$name]);

        return new MockResponse('', ['http_code' => 200]);
    }

    private function handleGet(string $name): MockResponse
    {
        if (!isset(self::$files[$name])) {
            return new MockResponse('', ['http_code' => 404]);
        }

        return new MockResponse(self::$files[$name], [
            'response_headers' => [
                'content-type' => 'application/octet-stream',
                'content-length' => (string) \strlen(self::$files[$name]),
            ],
        ]);
    }

    /** Le client normalise un corps en flux en closure : la vider pour la comparer. */
    private function readBody(mixed $body): string
    {
        if (\is_resource($body)) {
            return (string) stream_get_contents($body);
        }

        if ($body instanceof \Closure) {
            $content = '';
            while ('' !== $chunk = (string) $body(16372)) {
                $content .= $chunk;
            }

            return $content;
        }

        return (string) $body;
    }
}
