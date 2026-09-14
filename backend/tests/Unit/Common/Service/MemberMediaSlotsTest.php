<?php

namespace App\Tests\Unit\Common\Service;

use App\Common\Exception\UseCaseException;
use App\Common\Service\MemberMediaSeeder;
use App\Common\Service\MemberMediaSlots;
use App\Entity\Member;
use App\Repository\MemberDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refus du résolveur de slots. Les deux chemins nominaux (dossier racine et
 * dossier de saison) sont couverts de bout en bout par InscriptionDraftApiTest
 * et LicenseRequestApiTest ; ces deux-là sont injoignables par HTTP, le routeur
 * n'acceptant que les quatre `systemKey` connus.
 */
class MemberMediaSlotsTest extends TestCase
{
    public function testAnUnknownSystemKeyIsRejected(): void
    {
        $slots = $this->makeSlots($this->createStub(MemberDocumentRepository::class));

        try {
            $slots->resolve(new Member(), '2026-2027', 'passeport');
            $this->fail('Une clé inconnue doit être refusée');
        } catch (UseCaseException $e) {
            $this->assertSame('Type de document invalide', $e->getMessage());
            $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $e->getCode());
        }
    }

    /**
     * Arrive quand le dossier de saison existe déjà mais sans le slot demandé —
     * cas d'un dossier créé avant l'ajout d'une nouvelle pièce par défaut, le
     * seeder ne complétant pas un dossier existant.
     */
    public function testAMissingSlotIsA404(): void
    {
        $repository = $this->createStub(MemberDocumentRepository::class);
        $repository->method('findDefaultSlot')->willReturn(null);

        try {
            $this->makeSlots($repository)->resolve(new Member(), '2026-2027', 'attestation');
            $this->fail('Un slot absent doit être signalé');
        } catch (UseCaseException $e) {
            $this->assertSame('Slot médiathèque introuvable', $e->getMessage());
            $this->assertSame(Response::HTTP_NOT_FOUND, $e->getCode());
        }
    }

    private function makeSlots(MemberDocumentRepository $repository): MemberMediaSlots
    {
        return new MemberMediaSlots(
            $repository,
            $this->createStub(MemberMediaSeeder::class),
            $this->createStub(EntityManagerInterface::class),
        );
    }
}
