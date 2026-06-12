<?php

namespace App\Tests\Functional;

use App\Entity\SalleClosure;
use App\Tests\Support\ApiTestCase;

/**
 * /api/salle-closure — lecture pour tout utilisateur connecté, écriture super admin.
 */
class SalleClosureApiTest extends ApiTestCase
{
    public function testAnyAuthenticatedUserListsClosuresOrderedByDate(): void
    {
        $this->aClosure()->from('+30 days')->to('+31 days')->because('Travaux')->persist();
        $this->aClosure()->from('+2 days')->to('+2 days')->because('Férié')->persist();

        $this->actingAsUser();
        $this->getJson('/api/salle-closure');

        $body = $this->assertJsonResponse(200);
        $this->assertCount(2, $body);
        $this->assertSame('Férié', $body[0]['reason']);
    }

    public function testUnauthenticatedIsRejected(): void
    {
        $this->getJson('/api/salle-closure');

        $this->assertJsonResponse(401);
    }

    public function testSuperAdminCreatesClosure(): void
    {
        $this->actingAsSuperAdmin();
        $this->postJson('/api/salle-closure', [
            'startDate' => '2026-08-01',
            'endDate' => '2026-08-15',
            'reason' => 'Fermeture estivale',
        ]);

        $body = $this->assertJsonResponse(200);
        $this->assertSame('2026-08-01', $body['startDate']);
        $this->assertSame('2026-08-15', $body['endDate']);
        $this->assertSame('Fermeture estivale', $body['reason']);
    }

    public function testClosureWithEndBeforeStartIsRejected(): void
    {
        $this->actingAsSuperAdmin();
        $this->postJson('/api/salle-closure', [
            'startDate' => '2026-08-15',
            'endDate' => '2026-08-01',
        ]);

        $this->assertJsonResponse(422);
    }

    public function testOverlappingClosureIsRejected(): void
    {
        $this->aClosure()->from('2026-08-01')->to('2026-08-10')->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/salle-closure', [
            'startDate' => '2026-08-05',
            'endDate' => '2026-08-20',
        ]);

        $this->assertJsonResponse(422);
    }

    public function testClosureWithoutDatesIsRejected(): void
    {
        $this->actingAsSuperAdmin();
        $this->postJson('/api/salle-closure', ['reason' => 'Sans dates']);

        $this->assertJsonResponse(422);
    }

    public function testAdminCannotCreateClosure(): void
    {
        $this->actingAsAdmin();
        $this->postJson('/api/salle-closure', [
            'startDate' => '2026-08-01',
            'endDate' => '2026-08-02',
        ]);

        $this->assertJsonResponse(403);
    }

    public function testSuperAdminDeletesClosure(): void
    {
        $closure = $this->aClosure()->persist();
        // Doctrine nulls the id of the in-memory instance on deletion: keep a copy
        $closureId = $closure->getId();

        $this->actingAsSuperAdmin();
        $this->deleteJson('/api/salle-closure/'.$closureId);

        $this->assertJsonResponse(200);
        $this->assertNull($this->em()->getRepository(SalleClosure::class)->find($closureId));
    }

    public function testDeleteUnknownClosureReturns404(): void
    {
        $this->actingAsSuperAdmin();
        $this->deleteJson('/api/salle-closure/999999');

        $this->assertJsonResponse(404);
    }

    public function testPlainUserCannotDeleteClosure(): void
    {
        $closure = $this->aClosure()->persist();

        $this->actingAsUser();
        $this->deleteJson('/api/salle-closure/'.$closure->getId());

        $this->assertJsonResponse(403);
    }
}
