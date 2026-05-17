<?php

namespace App\Common\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RecaptchaVerifier
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'RECAPTCHA_SECRET_KEY')]
        private readonly string $secretKey,
    ) {
    }

    /**
     * Returns true when the token is accepted by Google, or when the secret
     * key is unset (local dev convenience — captcha disabled).
     */
    public function verify(string $token): bool
    {
        if ($this->secretKey === '') {
            return true;
        }

        if ($token === '') {
            return false;
        }

        try {
            $response = $this->httpClient->request('POST', self::VERIFY_URL, [
                'body' => [
                    'secret' => $this->secretKey,
                    'response' => $token,
                ],
            ]);
            $data = $response->toArray(false);
        } catch (HttpExceptionInterface $e) {
            $this->logger->error('reCAPTCHA verification request failed', ['exception' => $e]);

            return false;
        }

        if (($data['success'] ?? false) !== true) {
            $this->logger->warning('reCAPTCHA verification rejected token', [
                'error-codes' => $data['error-codes'] ?? [],
            ]);

            return false;
        }

        return true;
    }
}
