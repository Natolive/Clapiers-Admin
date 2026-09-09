<?php

namespace App\Tests\Unit\Common\Service;

use App\Common\Service\InscriptionDraftPurger;
use App\Common\Service\MemberMediaStorage;
use App\Repository\InscriptionDraftRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Garde-fous de la purge. Le chemin nominal (un brouillon inactif emporte ses
 * fichiers) est couvert par InscriptionDraftApiTest, à travers l'API.
 */
class InscriptionDraftPurgerTest extends TestCase
{
    public function testThePurgeRunsOnlyOncePerProcess(): void
    {
        $drafts = $this->createMock(InscriptionDraftRepository::class);
        $drafts->expects($this->once())->method('findInactiveSince')->willReturn([]);

        $purger = new InscriptionDraftPurger(
            $drafts,
            $this->createStub(MemberMediaStorage::class),
            $this->createStub(EntityManagerInterface::class),
            new NullLogger(),
        );

        $this->assertSame(0, $purger->purgeOnce());
        $this->assertSame(0, $purger->purgeOnce(), 'Le second appel ne doit plus rien tenter');
    }

    /** Journaliser ne suffit pas : le ménage ne doit jamais casser l'inscription en cours. */
    public function testAFailingPurgeIsLoggedAndSwallowed(): void
    {
        $drafts = $this->createStub(InscriptionDraftRepository::class);
        $drafts->method('findInactiveSince')->willThrowException(new \RuntimeException('base indisponible'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')
            ->with("Purge des brouillons d'inscription impossible", $this->anything());

        $purger = new InscriptionDraftPurger(
            $drafts,
            $this->createStub(MemberMediaStorage::class),
            $this->createStub(EntityManagerInterface::class),
            $logger,
        );

        $this->assertSame(0, $purger->purgeOnce());
    }
}
