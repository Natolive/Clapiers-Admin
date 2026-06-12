<?php

namespace App\Tests\Unit\Common\Service;

use App\Common\Service\RecaptchaVerifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RecaptchaVerifierTest extends TestCase
{
    public function testEmptySecretKeyBypassesVerification(): void
    {
        $httpClient = new MockHttpClient(function (): never {
            self::fail('No HTTP call should be made when the secret key is empty');
        });

        $verifier = $this->makeVerifier($httpClient, secretKey: '');

        $this->assertTrue($verifier->verify('any-token'));
    }

    public function testEmptyTokenIsRejectedWithoutHttpCall(): void
    {
        $httpClient = new MockHttpClient(function (): never {
            self::fail('No HTTP call should be made for an empty token');
        });

        $verifier = $this->makeVerifier($httpClient);

        $this->assertFalse($verifier->verify(''));
    }

    public function testTokenAcceptedByGoogleIsValid(): void
    {
        $httpClient = new MockHttpClient(
            new MockResponse(json_encode(['success' => true]))
        );

        $verifier = $this->makeVerifier($httpClient);

        $this->assertTrue($verifier->verify('valid-token'));
    }

    public function testTokenRejectedByGoogleIsInvalid(): void
    {
        $httpClient = new MockHttpClient(
            new MockResponse(json_encode(['success' => false, 'error-codes' => ['invalid-input-response']]))
        );

        $verifier = $this->makeVerifier($httpClient);

        $this->assertFalse($verifier->verify('rejected-token'));
    }

    public function testHttpFailureIsTreatedAsInvalid(): void
    {
        $httpClient = new MockHttpClient(function (): never {
            throw new TransportException('Network unreachable');
        });

        $verifier = $this->makeVerifier($httpClient);

        $this->assertFalse($verifier->verify('any-token'));
    }

    public function testSecretAndTokenAreSentToGoogle(): void
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://www.google.com/recaptcha/api/siteverify', $url);
            $this->assertStringContainsString('secret=s3cret', $options['body']);
            $this->assertStringContainsString('response=the-token', $options['body']);

            return new MockResponse(json_encode(['success' => true]));
        });

        $verifier = $this->makeVerifier($httpClient, secretKey: 's3cret');

        $this->assertTrue($verifier->verify('the-token'));
    }

    private function makeVerifier(HttpClientInterface $httpClient, string $secretKey = 's3cret'): RecaptchaVerifier
    {
        return new RecaptchaVerifier($httpClient, new NullLogger(), $secretKey);
    }
}
