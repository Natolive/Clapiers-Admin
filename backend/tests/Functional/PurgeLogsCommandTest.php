<?php

namespace App\Tests\Functional;

use App\Command\PurgeLogsCommand;
use App\Entity\Log;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Rétention des logs : la commande app:logs:purge supprime les lignes plus
 * anciennes que la fenêtre de rétention (14 jours par défaut).
 */
class PurgeLogsCommandTest extends ApiTestCase
{
    private function persistLog(string $message, \DateTimeImmutable $at): void
    {
        $log = (new Log())
            ->setLevel('warning')
            ->setChannel('app')
            ->setMessage($message)
            ->setCreatedAt($at);

        $this->em()->persist($log);
        $this->em()->flush();
    }

    private function tester(): CommandTester
    {
        return new CommandTester(static::getContainer()->get(PurgeLogsCommand::class));
    }

    public function testPurgesLogsOlderThanRetentionWindow(): void
    {
        $this->persistLog('Vieux', new \DateTimeImmutable('-20 days'));
        $this->persistLog('Récent', new \DateTimeImmutable('-2 days'));

        $tester = $this->tester();
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $remaining = $this->em()->getRepository(Log::class)->findAll();
        $this->assertCount(1, $remaining);
        $this->assertSame('Récent', $remaining[0]->getMessage());
    }

    public function testCustomRetentionWindow(): void
    {
        $this->persistLog('Trois jours', new \DateTimeImmutable('-3 days'));

        $tester = $this->tester();
        $tester->execute(['--days' => 1]);

        $tester->assertCommandIsSuccessful();
        $this->assertSame(0, $this->em()->getRepository(Log::class)->count([]));
    }

    public function testRejectsNonPositiveRetention(): void
    {
        $tester = $this->tester();
        $exitCode = $tester->execute(['--days' => 0]);

        $this->assertNotSame(0, $exitCode);
    }
}
