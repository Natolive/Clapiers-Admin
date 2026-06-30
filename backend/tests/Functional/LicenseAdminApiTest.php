<?php

namespace App\Tests\Functional;

use App\Entity\Enum\LicenseStatus;
use App\Entity\Enum\MemberStatus;
use App\Entity\License;
use App\Tests\Support\ApiTestCase;

/**
 * Back-office des licences (SUPER_ADMIN) : liste, tarifs HelloAsso,
 * validation et refus. Le client HelloAsso est remplacé par un fake en test.
 */
class LicenseAdminApiTest extends ApiTestCase
{
    public function testListReturnsPaginatedLicenses(): void
    {
        $this->aLicense()->persist();
        $this->aLicense()->persist();
        $this->actingAsSuperAdmin();

        $this->getJson('/api/license/paginated');

        $body = $this->assertJsonResponse(200);
        $this->assertSame(2, $body['total']);
        $this->assertCount(2, $body['data']);
        $this->assertArrayHasKey('member', $body['data'][0]);
    }

    public function testListRequiresAuthentication(): void
    {
        $this->getJson('/api/license/paginated');
        $this->assertJsonResponse(401);
    }

    public function testListIsForbiddenForAdmin(): void
    {
        $this->actingAsAdmin();
        $this->getJson('/api/license/paginated');
        $this->assertJsonResponse(403);
    }

    public function testTiersReturnsHelloAssoTiers(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/license/tiers');

        $body = $this->assertJsonResponse(200);
        $this->assertSame(101, $body['data'][0]['id']);
        $this->assertSame('Licence Compétition', $body['data'][1]['label']);
    }

    public function testApproveFreezesAmountAndEmailsThePaymentLink(): void
    {
        $license = $this->aLicense()->withToken('tok-approve')->persist();
        $this->actingAsSuperAdmin();

        $this->postJson("/api/license/{$license->getId()}/approve", [
            'helloAssoTierId' => 102,
            'amount' => 12000,
        ]);

        $body = $this->assertJsonResponse(200);
        $this->assertSame('validee', $body['status']);
        $this->assertSame(12000, $body['amount']);
        $this->assertSame(102, $body['helloAssoTierId']);
        $this->assertSame('active', $body['member']['status']);

        $this->em()->clear();
        $reloaded = $this->em()->getRepository(License::class)->find($license->getId());
        $this->assertSame(LicenseStatus::VALIDEE, $reloaded->getStatus());
        $this->assertSame(MemberStatus::ACTIVE, $reloaded->getMember()->getStatus());
        $this->assertNotNull($reloaded->getTokenExpiresAt());

        $this->assertEmailCount(1);
        $this->assertEmailHtmlBodyContains($this->getMailerMessage(), '/licence/tok-approve');
    }

    public function testApproveUnknownReturns404(): void
    {
        $this->actingAsSuperAdmin();
        $this->postJson('/api/license/999999/approve', ['helloAssoTierId' => 1, 'amount' => 100]);
        $this->assertJsonResponse(404);
    }

    public function testApproveAlreadyTreatedReturns409(): void
    {
        $license = $this->aLicense()->withStatus(LicenseStatus::VALIDEE)->persist();
        $this->actingAsSuperAdmin();

        $this->postJson("/api/license/{$license->getId()}/approve", ['helloAssoTierId' => 1, 'amount' => 100]);

        $this->assertJsonResponse(409);
    }

    public function testApproveValidationFailsOnNonPositiveAmount(): void
    {
        $license = $this->aLicense()->persist();
        $this->actingAsSuperAdmin();

        $this->postJson("/api/license/{$license->getId()}/approve", ['helloAssoTierId' => 1, 'amount' => 0]);

        $this->assertJsonResponse(422);
    }

    public function testApproveIsForbiddenForAdmin(): void
    {
        $license = $this->aLicense()->persist();
        $this->actingAsAdmin();
        $this->postJson("/api/license/{$license->getId()}/approve", ['helloAssoTierId' => 1, 'amount' => 100]);
        $this->assertJsonResponse(403);
    }

    public function testRejectSetsStatusAndEmails(): void
    {
        $license = $this->aLicense()->persist();
        $this->actingAsSuperAdmin();

        $this->postJson("/api/license/{$license->getId()}/reject", ['reason' => 'Certificat médical illisible']);

        $body = $this->assertJsonResponse(200);
        $this->assertSame('refusee', $body['status']);
        $this->assertSame('rejected', $body['member']['status']);
        $this->assertSame('Certificat médical illisible', $body['rejectionReason']);
        $this->assertEmailCount(1);
    }

    public function testRejectValidationFailsOnEmptyReason(): void
    {
        $license = $this->aLicense()->persist();
        $this->actingAsSuperAdmin();
        $this->postJson("/api/license/{$license->getId()}/reject", ['reason' => '']);
        $this->assertJsonResponse(422);
    }

    public function testRejectUnknownReturns404(): void
    {
        $this->actingAsSuperAdmin();
        $this->postJson('/api/license/999999/reject', ['reason' => 'x']);
        $this->assertJsonResponse(404);
    }

    public function testRejectAlreadyTreatedReturns409(): void
    {
        $license = $this->aLicense()->withStatus(LicenseStatus::REFUSEE)->persist();
        $this->actingAsSuperAdmin();

        $this->postJson("/api/license/{$license->getId()}/reject", ['reason' => 'x']);

        $this->assertJsonResponse(409);
    }

    public function testListFiltersByStatusAndSearch(): void
    {
        $zidane = $this->aMember()->named('Zinedine', 'Zidane')->persist();
        $this->aLicense()->forMember($zidane)->withStatus(LicenseStatus::SOUMISE)->persist();
        $this->aLicense()->withStatus(LicenseStatus::VALIDEE)->persist();
        $this->actingAsSuperAdmin();

        $this->getJson('/api/license/paginated?status=soumise&search=zidane');

        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertSame('Zidane', $body['data'][0]['member']['lastName']);
    }
}
