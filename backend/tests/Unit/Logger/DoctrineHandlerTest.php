<?php

namespace App\Tests\Unit\Logger;

use App\Logger\DoctrineHandler;
use Doctrine\DBAL\Connection;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

/**
 * Le DoctrineHandler est neutralisé en environnement de test (handler Monolog
 * « null »), il est donc couvert ici en isolation avec une Connection mockée.
 */
class DoctrineHandlerTest extends TestCase
{
    private function record(Level $level, string $message = 'Boom', array $context = []): LogRecord
    {
        return new LogRecord(
            new \DateTimeImmutable('2026-01-01 10:00:00'),
            'app',
            $level,
            $message,
            $context,
        );
    }

    public function testPersistsWarningAndAbove(): void
    {
        $captured = null;
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('insert')
            ->with('log', $this->callback(function (array $data) use (&$captured) {
                $captured = $data;

                return true;
            }));

        $handler = new DoctrineHandler($connection);
        $handler->handle($this->record(Level::Error, 'Database down'));

        $this->assertSame('error', $captured['level']);
        $this->assertSame('app', $captured['channel']);
        $this->assertSame('Database down', $captured['message']);
        $this->assertSame('2026-01-01 10:00:00', $captured['created_at']);
    }

    public function testIgnoresBelowMinimumLevel(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('insert');

        $handler = new DoctrineHandler($connection);
        $handler->handle($this->record(Level::Debug));
        $handler->handle($this->record(Level::Info));
        $handler->handle($this->record(Level::Notice));
    }

    public function testNormalizesThrowableInContext(): void
    {
        $captured = null;
        $connection = $this->createStub(Connection::class);
        $connection->method('insert')->willReturnCallback(function (string $table, array $data) use (&$captured) {
            $captured = $data;

            return 1;
        });

        $handler = new DoctrineHandler($connection);
        $handler->handle($this->record(Level::Critical, 'Crash', ['exception' => new \RuntimeException('boom', 42)]));

        $decoded = json_decode((string) $captured['context'], true);
        $this->assertSame(\RuntimeException::class, $decoded['exception']['class']);
        $this->assertSame('boom', $decoded['exception']['message']);
        $this->assertSame(42, $decoded['exception']['code']);
    }

    public function testNullContextWhenEmpty(): void
    {
        $captured = null;
        $connection = $this->createStub(Connection::class);
        $connection->method('insert')->willReturnCallback(function (string $table, array $data) use (&$captured) {
            $captured = $data;

            return 1;
        });

        $handler = new DoctrineHandler($connection);
        $handler->handle($this->record(Level::Warning));

        $this->assertNull($captured['context']);
    }

    public function testPrunesOldLogsOncePerProcessWithDefaultRetention(): void
    {
        $thresholds = [];
        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturnCallback(function (string $sql, array $params) use (&$thresholds) {
            $this->assertStringContainsString('DELETE FROM log', $sql);
            $thresholds[] = $params['threshold'];

            return 0;
        });

        $handler = new DoctrineHandler($connection);
        $handler->handle($this->record(Level::Warning));
        $handler->handle($this->record(Level::Error));

        // Une seule purge par process, quel que soit le nombre de logs écrits.
        $this->assertCount(1, $thresholds);
        // Seuil ~ maintenant - 14 jours.
        $this->assertLessThan((new \DateTimeImmutable('-13 days'))->format('Y-m-d H:i:s'), $thresholds[0]);
        $this->assertGreaterThan((new \DateTimeImmutable('-15 days'))->format('Y-m-d H:i:s'), $thresholds[0]);
    }

    public function testHonoursCustomRetentionWindow(): void
    {
        $threshold = null;
        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturnCallback(function (string $sql, array $params) use (&$threshold) {
            $threshold = $params['threshold'];

            return 0;
        });

        $handler = new DoctrineHandler($connection, 2);
        $handler->handle($this->record(Level::Warning));

        $this->assertLessThan((new \DateTimeImmutable('-1 days'))->format('Y-m-d H:i:s'), $threshold);
        $this->assertGreaterThan((new \DateTimeImmutable('-3 days'))->format('Y-m-d H:i:s'), $threshold);
    }

    public function testSwallowsInsertFailure(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('insert')->willThrowException(new \RuntimeException('DB gone'));

        $handler = new DoctrineHandler($connection);

        // Ne doit pas propager l'exception : journaliser ne casse jamais la requête.
        $handler->handle($this->record(Level::Error));
        $this->addToAssertionCount(1);
    }
}
