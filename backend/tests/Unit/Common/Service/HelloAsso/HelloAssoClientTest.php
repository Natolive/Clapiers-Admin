<?php

namespace App\Tests\Unit\Common\Service\HelloAsso;

use App\Common\Exception\UseCaseException;
use App\Common\Service\HelloAsso\HelloAssoClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class HelloAssoClientTest extends TestCase
{
    private function makeClient(MockHttpClient $http): HelloAssoClient
    {
        return new HelloAssoClient(
            $http,
            new NullLogger(),
            new ArrayAdapter(),
            'https://api.helloasso-sandbox.com',
            'client-id',
            'client-secret',
            'mon-asso',
            'Membership',
            'adhesion-2026',
        );
    }

    private function jsonResponse(array $data, int $status = 200): MockResponse
    {
        return new MockResponse(json_encode($data, JSON_THROW_ON_ERROR), [
            'http_code' => $status,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    }

    public function testCreateCheckoutIntentAuthenticatesThenPostsWithBearer(): void
    {
        $token = $this->jsonResponse(['access_token' => 'tok-123', 'expires_in' => 1800]);
        $checkout = $this->jsonResponse(['id' => 42, 'redirectUrl' => 'https://pay.helloasso.com/x']);
        $http = new MockHttpClient([$token, $checkout]);

        $result = $this->makeClient($http)->createCheckoutIntent([
            'totalAmount' => 5000,
            'itemName' => 'Licence',
        ]);

        $this->assertSame(42, $result['id']);

        // First call hit the OAuth token endpoint
        $this->assertSame('POST', $token->getRequestMethod());
        $this->assertStringContainsString('/oauth2/token', $token->getRequestUrl());
        $this->assertStringContainsString('grant_type=client_credentials', $token->getRequestOptions()['body']);

        // Second call posted the checkout intent with the bearer token
        $this->assertSame('POST', $checkout->getRequestMethod());
        $this->assertStringContainsString('/v5/organizations/mon-asso/checkout-intents', $checkout->getRequestUrl());
        $headers = implode("\n", $checkout->getRequestOptions()['headers']);
        $this->assertStringContainsString('Bearer tok-123', $headers);
        $this->assertStringContainsString('totalAmount', $checkout->getRequestOptions()['body']);
    }

    public function testAccessTokenIsCachedAcrossCalls(): void
    {
        $http = new MockHttpClient([
            $this->jsonResponse(['access_token' => 'tok-123']),
            $this->jsonResponse(['id' => 1]),
            $this->jsonResponse(['id' => 2]),
        ]);
        $client = $this->makeClient($http);

        $client->createCheckoutIntent(['totalAmount' => 100]);
        $client->createCheckoutIntent(['totalAmount' => 200]);

        // 1 token request reused for 2 checkout calls = 3 total (not 4)
        $this->assertSame(3, $http->getRequestsCount());
    }

    public function testGetCheckoutIntentIssuesGet(): void
    {
        $token = $this->jsonResponse(['access_token' => 'tok-123']);
        $intent = $this->jsonResponse(['id' => 42, 'order' => ['id' => 99]]);
        $http = new MockHttpClient([$token, $intent]);

        $result = $this->makeClient($http)->getCheckoutIntent(42);

        $this->assertSame(99, $result['order']['id']);
        $this->assertSame('GET', $intent->getRequestMethod());
        $this->assertStringContainsString('/v5/organizations/mon-asso/checkout-intents/42', $intent->getRequestUrl());
    }

    public function testGetFormTiersReadsPublicFormTiersAndCaches(): void
    {
        $token = $this->jsonResponse(['access_token' => 'tok-123']);
        $form = $this->jsonResponse(['tiers' => [['id' => 7, 'label' => 'Senior', 'price' => 12000]]]);
        $http = new MockHttpClient([$token, $form]);
        $client = $this->makeClient($http);

        $first = $client->getFormTiers();
        $second = $client->getFormTiers(); // served from cache, no extra request

        // Les tiers du formulaire public sont normalisés en {id, label, amount}
        $this->assertSame(['id' => 7, 'label' => 'Senior', 'amount' => 12000], $first['data'][0]);
        $this->assertStringContainsString('/forms/Membership/adhesion-2026/public', $form->getRequestUrl());
        $this->assertSame($first, $second);
        $this->assertSame(2, $http->getRequestsCount());
    }

    public function testApiErrorIsWrappedInUseCaseException(): void
    {
        $http = new MockHttpClient([
            $this->jsonResponse(['access_token' => 'tok-123']),
            $this->jsonResponse(['message' => 'boom'], 500),
        ]);

        $this->expectException(UseCaseException::class);
        $this->expectExceptionMessage('Erreur de communication avec HelloAsso.');

        $this->makeClient($http)->getCheckoutIntent(1);
    }

    public function testFailedAuthenticationIsWrapped(): void
    {
        $http = new MockHttpClient([$this->jsonResponse(['error' => 'invalid_client'], 401)]);

        $this->expectException(UseCaseException::class);
        $this->expectExceptionMessage('Authentification HelloAsso échouée.');

        $this->makeClient($http)->getCheckoutIntent(1);
    }

    public function testMissingAccessTokenIsRejected(): void
    {
        $http = new MockHttpClient([$this->jsonResponse(['expires_in' => 1800])]);

        $this->expectException(UseCaseException::class);
        $this->expectExceptionMessage('Token HelloAsso absent de la réponse.');

        $this->makeClient($http)->createCheckoutIntent(['totalAmount' => 100]);
    }
}
