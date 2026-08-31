<?php

namespace App\Tests\Functional;

use App\Entity\Log;
use App\Tests\Support\ApiTestCase;

/**
 * Consultation et purge des logs applicatifs (SUPER_ADMIN). Les logs sont
 * persistés en base par le DoctrineHandler (couvert par un test unitaire) ;
 * ici on les crée directement pour piloter les endpoints de lecture.
 */
class LogApiTest extends ApiTestCase
{
    private function persistLog(
        string $level,
        string $message,
        string $channel = 'app',
        ?string $context = null,
        ?\DateTimeImmutable $at = null,
    ): Log {
        $log = (new Log())
            ->setLevel($level)
            ->setChannel($channel)
            ->setMessage($message)
            ->setContext($context)
            ->setCreatedAt($at ?? new \DateTimeImmutable('now'));

        $this->em()->persist($log);
        $this->em()->flush();

        return $log;
    }

    public function testListReturnsPaginatedLogs(): void
    {
        $this->persistLog('error', 'Boom');
        $this->persistLog('warning', 'Attention');
        $this->actingAsSuperAdmin();

        $this->getJson('/api/logs/paginated');

        $body = $this->assertJsonResponse(200);
        $this->assertSame(2, $body['total']);
        $this->assertCount(2, $body['data']);
        $this->assertArrayHasKey('level', $body['data'][0]);
        $this->assertArrayHasKey('channel', $body['data'][0]);
        $this->assertArrayHasKey('message', $body['data'][0]);
        $this->assertArrayHasKey('createdAt', $body['data'][0]);
    }

    public function testListDecodesJsonContext(): void
    {
        $this->persistLog('error', 'Boom', 'app', json_encode(['exception' => ['class' => 'RuntimeException']]));
        $this->actingAsSuperAdmin();

        $this->getJson('/api/logs/paginated');

        $body = $this->assertJsonResponse(200);
        $this->assertSame('RuntimeException', $body['data'][0]['context']['exception']['class']);
    }

    public function testListRequiresAuthentication(): void
    {
        $this->getJson('/api/logs/paginated');
        $this->assertJsonResponse(401);
    }

    public function testListIsForbiddenForAdmin(): void
    {
        $this->actingAsAdmin();
        $this->getJson('/api/logs/paginated');
        $this->assertJsonResponse(403);
    }

    public function testListFiltersByLevel(): void
    {
        $this->persistLog('error', 'Boom');
        $this->persistLog('warning', 'Attention');
        $this->actingAsSuperAdmin();

        $this->getJson('/api/logs/paginated?level=error');

        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertSame('Boom', $body['data'][0]['message']);
    }

    public function testListFiltersBySearch(): void
    {
        $this->persistLog('error', 'Database connection lost');
        $this->persistLog('error', 'User not found');
        $this->actingAsSuperAdmin();

        $this->getJson('/api/logs/paginated?search=database');

        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertSame('Database connection lost', $body['data'][0]['message']);
    }

    public function testListOrdersNewestFirst(): void
    {
        $this->persistLog('warning', 'Ancien', 'app', null, new \DateTimeImmutable('2020-01-01 10:00:00'));
        $this->persistLog('warning', 'Récent', 'app', null, new \DateTimeImmutable('2026-01-01 10:00:00'));
        $this->actingAsSuperAdmin();

        $this->getJson('/api/logs/paginated');

        $body = $this->assertJsonResponse(200);
        $this->assertSame('Récent', $body['data'][0]['message']);
        $this->assertSame('Ancien', $body['data'][1]['message']);
    }

    public function testClearDeletesAllLogs(): void
    {
        $this->persistLog('error', 'Boom');
        $this->persistLog('warning', 'Attention');
        $this->actingAsSuperAdmin();

        $this->deleteJson('/api/logs');

        $body = $this->assertJsonResponse(200);
        $this->assertSame(2, $body['deleted']);
        $this->assertSame(0, $this->em()->getRepository(Log::class)->count([]));
    }

    public function testClearRequiresAuthentication(): void
    {
        $this->deleteJson('/api/logs');
        $this->assertJsonResponse(401);
    }

    public function testClearIsForbiddenForAdmin(): void
    {
        $this->actingAsAdmin();
        $this->deleteJson('/api/logs');
        $this->assertJsonResponse(403);
    }
}
