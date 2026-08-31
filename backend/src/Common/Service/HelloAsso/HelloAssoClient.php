<?php

namespace App\Common\Service\HelloAsso;

use App\Common\Exception\UseCaseException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin client over the HelloAsso v5 API.
 *
 * Credentials, organisation and membership-form come from
 * {@see HelloAssoConfigProvider} (admin setting first, env as fallback).
 *
 * Authentication uses the OAuth2 client-credentials grant; the access token
 * (valid 30 min on HelloAsso's side) is cached ~25 min to avoid re-authenticating
 * on every call. Network/HTTP failures are logged and surfaced as a
 * UseCaseException (502) so callers can report a clean error.
 */
class HelloAssoClient implements HelloAssoClientInterface
{
    /** Publiques : le provider les purge quand la configuration change. */
    public const TOKEN_CACHE_KEY = 'helloasso.access_token';
    public const TIERS_CACHE_KEY = 'helloasso.form_tiers';

    private const TOKEN_TTL = 1500; // 25 min — safety margin under HelloAsso's 30 min
    private const TIERS_TTL = 600;  // 10 min

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache,
        private readonly HelloAssoConfigProvider $config,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public function createCheckoutIntent(array $body): array
    {
        return $this->request(
            'POST',
            sprintf('/v5/organizations/%s/checkout-intents', $this->config->get('organizationSlug')),
            ['json' => $body],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getCheckoutIntent(int $checkoutIntentId): array
    {
        return $this->request(
            'GET',
            sprintf('/v5/organizations/%s/checkout-intents/%d', $this->config->get('organizationSlug'), $checkoutIntentId),
        );
    }

    /**
     * Tariff tiers configured on the club's HelloAsso membership form.
     * Cached briefly: tariffs change rarely and are read on each validation screen.
     *
     * @return array<string, mixed>
     */
    public function getFormTiers(): array
    {
        return $this->cache->get(self::TIERS_CACHE_KEY, function (ItemInterface $item) use (&$unused): array {
            $item->expiresAfter(self::TIERS_TTL);

            // Le catalogue des tarifs est exposé sous "tiers" par l'endpoint public
            // du formulaire (l'endpoint /items renvoie les articles *vendus*).
            $form = $this->request(
                'GET',
                sprintf(
                    '/v5/organizations/%s/forms/%s/%s/public',
                    $this->config->get('organizationSlug'),
                    $this->config->get('membershipFormType'),
                    $this->config->get('membershipFormSlug'),
                ),
            );

            $tiers = array_map(static fn (array $tier): array => [
                'id' => $tier['id'] ?? null,
                'label' => $tier['label'] ?? '',
                'amount' => $tier['price'] ?? 0,
            ], $form['tiers'] ?? []);

            return ['data' => $tiers];
        });
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $options = []): array
    {
        $options['auth_bearer'] = $this->getAccessToken();

        try {
            $response = $this->httpClient->request($method, $this->config->get('baseUrl').$path, $options);

            return $response->toArray();
        } catch (HttpExceptionInterface $e) {
            $this->logger->error('HelloAsso API request failed', [
                'method' => $method,
                'path' => $path,
                'exception' => $e,
            ]);

            throw new UseCaseException('Erreur de communication avec HelloAsso.', Response::HTTP_BAD_GATEWAY);
        }
    }

    private function getAccessToken(): string
    {
        return $this->cache->get(self::TOKEN_CACHE_KEY, function (ItemInterface $item): string {
            $item->expiresAfter(self::TOKEN_TTL);

            try {
                $response = $this->httpClient->request('POST', $this->config->get('baseUrl').'/oauth2/token', [
                    'body' => [
                        'grant_type' => 'client_credentials',
                        'client_id' => $this->config->get('clientId'),
                        'client_secret' => $this->config->get('clientSecret'),
                    ],
                ]);
                $data = $response->toArray();
            } catch (HttpExceptionInterface $e) {
                $this->logger->error('HelloAsso OAuth token request failed', ['exception' => $e]);

                throw new UseCaseException('Authentification HelloAsso échouée.', Response::HTTP_BAD_GATEWAY);
            }

            $token = $data['access_token'] ?? null;
            if (!is_string($token) || $token === '') {
                throw new UseCaseException('Token HelloAsso absent de la réponse.', Response::HTTP_BAD_GATEWAY);
            }

            return $token;
        });
    }
}
