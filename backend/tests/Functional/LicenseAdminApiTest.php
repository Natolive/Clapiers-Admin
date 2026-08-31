<?php

namespace App\Tests\Functional;

use App\Entity\Enum\LicenseStatus;
use App\Entity\Enum\MemberStatus;
use App\Entity\License;
use App\Entity\Member;
use App\Entity\MemberDocument;
use App\Repository\MemberDocumentRepository;
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

    public function testListFiltersBySeason(): void
    {
        $this->aLicense()->inSeason('2030-2031')->persist();
        $this->aLicense()->inSeason('2030-2031')->persist();
        $this->aLicense()->inSeason('2031-2032')->persist();
        $this->actingAsSuperAdmin();

        $this->getJson('/api/license/paginated?season=2030-2031');

        $body = $this->assertJsonResponse(200);
        $this->assertSame(2, $body['total']);
        $this->assertCount(2, $body['data']);
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

    public function testGetReviewReturnsRequestInfoAndDocuments(): void
    {
        $license = $this->aLicense()->withToken('tok-review')->inSeason('2030-2031')->persist();

        // Dépôt public de deux pièces sur quatre attendues.
        $this->uploadFile('/api/public/license-request/tok-review/document/identity_photo', $this->fakePng());
        $this->uploadFile('/api/public/license-request/tok-review/document/medical_certificate', $this->fakePdf());

        $this->actingAsSuperAdmin();
        $this->getJson("/api/license/{$license->getId()}");

        $body = $this->assertJsonResponse(200);

        // Toutes les infos de la demande.
        $this->assertSame($license->getId(), $body['license']['id']);
        $this->assertArrayHasKey('member', $body['license']);
        $this->assertSame($license->getMember()->getId(), $body['memberId']);

        // État des pièces, dans l'ordre attendu.
        $this->assertCount(4, $body['documents']);
        $byKey = [];
        foreach ($body['documents'] as $document) {
            $byKey[$document['key']] = $document;
        }

        $this->assertTrue($byKey['identity_photo']['uploaded']);
        $this->assertNotNull($byKey['identity_photo']['nodeId']);
        $this->assertSame('image/png', $byKey['identity_photo']['mimeType']);

        $this->assertTrue($byKey['medical_certificate']['uploaded']);
        $this->assertNotNull($byKey['medical_certificate']['nodeId']);

        $this->assertFalse($byKey['id_card']['uploaded']);
        $this->assertNull($byKey['id_card']['nodeId']);
        $this->assertFalse($byKey['attestation']['uploaded']);

        // Le nodeId renvoyé est réellement téléchargeable via la médiathèque du membre.
        $this->client->request('GET', "/api/member/{$body['memberId']}/media/node/{$byKey['identity_photo']['nodeId']}/download");
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    public function testGetReviewRequiresAuthentication(): void
    {
        $license = $this->aLicense()->persist();
        $this->getJson("/api/license/{$license->getId()}");
        $this->assertJsonResponse(401);
    }

    public function testGetReviewIsForbiddenForAdmin(): void
    {
        $license = $this->aLicense()->persist();
        $this->actingAsAdmin();
        $this->getJson("/api/license/{$license->getId()}");
        $this->assertJsonResponse(403);
    }

    public function testGetReviewUnknownReturns404(): void
    {
        $this->actingAsSuperAdmin();
        $this->getJson('/api/license/999999');
        $this->assertJsonResponse(404);
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

    public function testGetReviewSurfacesExistingMemberSameEmail(): void
    {
        $requestMember = $this->aMember()->withEmail('dup@test.fr')->persist();
        $license = $this->aLicense()->forMember($requestMember)->persist();
        $existing = $this->aMember()->named('Zinedine', 'Zidane')->withEmail('dup@test.fr')->persist();
        $this->actingAsSuperAdmin();

        $this->getJson("/api/license/{$license->getId()}");

        $body = $this->assertJsonResponse(200);
        $this->assertNotNull($body['existingMember']);
        $this->assertSame($existing->getId(), $body['existingMember']['id']);
        $this->assertSame('Zidane', $body['existingMember']['lastName']);
        $this->assertFalse($body['existingMember']['hasLicenseThisSeason']);
    }

    public function testGetReviewHasNoExistingMemberWhenEmailUnique(): void
    {
        $license = $this->aLicense()->persist();
        $this->actingAsSuperAdmin();

        $this->getJson("/api/license/{$license->getId()}");

        $body = $this->assertJsonResponse(200);
        $this->assertNull($body['existingMember']);
    }

    public function testApproveWithReplaceMergesIntoExistingMember(): void
    {
        $requestMember = $this->aMember()->named('Jean', 'Dupont')->withEmail('jean@test.fr')->persist();
        $license = $this->aLicense()->forMember($requestMember)->withToken('tok-merge')->inSeason('2030-2031')->persist();
        $requestMemberId = $requestMember->getId();

        // Pièces déposées via le magic link (sur la fiche de la demande).
        $this->uploadFile('/api/public/license-request/tok-merge/document/identity_photo', $this->fakePng());
        $this->uploadFile('/api/public/license-request/tok-merge/document/medical_certificate', $this->fakePdf());

        $existing = $this->aMember()->named('Jean', 'Dupont')->withEmail('jean@test.fr')->persist();
        $existingId = $existing->getId();

        $this->actingAsSuperAdmin();
        $this->postJson("/api/license/{$license->getId()}/approve", [
            'helloAssoTierId' => 102,
            'amount' => 12000,
            'replaceMemberId' => $existingId,
        ]);

        $body = $this->assertJsonResponse(200);
        $this->assertSame('validee', $body['status']);
        $this->assertSame($existingId, $body['member']['id']);
        $this->assertSame('active', $body['member']['status']);

        // La fiche en double est supprimée.
        $this->em()->clear();
        $this->assertNull($this->em()->getRepository(Member::class)->find($requestMemberId));

        // Les pièces déposées ont été déplacées vers le membre existant.
        $reloaded = $this->em()->getRepository(Member::class)->find($existingId);
        $docRepo = $this->em()->getRepository(MemberDocument::class);
        $this->assertTrue($docRepo->findRootDocumentSlot($reloaded, 'identity_photo')->hasFile());
        $this->assertTrue($docRepo->findDefaultSlot($reloaded, '2030-2031', 'medical_certificate')->hasFile());

        $this->assertEmailCount(1);
    }

    public function testApproveWithReplaceBlocksWhenExistingHasLicenseSameSeason(): void
    {
        $requestMember = $this->aMember()->withEmail('busy@test.fr')->persist();
        $license = $this->aLicense()->forMember($requestMember)->inSeason('2030-2031')->persist();
        $existing = $this->aMember()->withEmail('busy@test.fr')->persist();
        $this->aLicense()->forMember($existing)->inSeason('2030-2031')->withStatus(LicenseStatus::VALIDEE)->persist();
        $this->actingAsSuperAdmin();

        $this->postJson("/api/license/{$license->getId()}/approve", [
            'helloAssoTierId' => 1,
            'amount' => 100,
            'replaceMemberId' => $existing->getId(),
        ]);

        $this->assertJsonResponse(409);
    }

    public function testApproveWithReplaceRejectsEmailMismatch(): void
    {
        $requestMember = $this->aMember()->withEmail('a@test.fr')->persist();
        $license = $this->aLicense()->forMember($requestMember)->persist();
        $other = $this->aMember()->withEmail('b@test.fr')->persist();
        $this->actingAsSuperAdmin();

        $this->postJson("/api/license/{$license->getId()}/approve", [
            'helloAssoTierId' => 1,
            'amount' => 100,
            'replaceMemberId' => $other->getId(),
        ]);

        $this->assertJsonResponse(422);
    }

    public function testResendPaymentLinkRenewsTheTokenAndEmailsIt(): void
    {
        $license = $this->aLicense()
            ->withToken('tok-perime')
            ->withStatus(LicenseStatus::VALIDEE)
            ->withAmount(12000)
            ->withTokenExpiringAt('-1 day')
            ->persist();
        $id = $license->getId();
        $this->actingAsSuperAdmin();

        $this->postJson("/api/license/{$id}/resend-link", []);

        $body = $this->assertJsonResponse(200);
        $this->assertNotSame('tok-perime', $body['accessToken']);
        $this->assertSame('validee', $body['status']);
        $this->assertEmailCount(1);

        $this->em()->clear();
        $reloaded = $this->em()->getRepository(License::class)->find($id);
        $this->assertGreaterThan(new \DateTimeImmutable('now'), $reloaded->getTokenExpiresAt());

        // L'ancien lien ne donne plus rien.
        $this->getJson('/api/public/license/tok-perime');
        $this->assertJsonResponse(404);
    }

    public function testResendPaymentLinkOnUnpayableLicenseReturns409(): void
    {
        $submitted = $this->aLicense()->withStatus(LicenseStatus::SOUMISE)->persist();
        $paid = $this->aLicense()->withStatus(LicenseStatus::PAYEE)->withAmount(12000)->persist();
        $this->actingAsSuperAdmin();

        $this->postJson("/api/license/{$submitted->getId()}/resend-link", []);
        $this->assertJsonResponse(409);

        $this->postJson("/api/license/{$paid->getId()}/resend-link", []);
        $this->assertJsonResponse(409);
    }

    public function testResendPaymentLinkUnknownReturns404(): void
    {
        $this->actingAsSuperAdmin();
        $this->postJson('/api/license/999999/resend-link', []);
        $this->assertJsonResponse(404);
    }

    public function testResendPaymentLinkRequiresSuperAdmin(): void
    {
        $license = $this->aLicense()->withStatus(LicenseStatus::VALIDEE)->withAmount(12000)->persist();

        $this->postJson("/api/license/{$license->getId()}/resend-link", []);
        $this->assertJsonResponse(401);

        $this->actingAsAdmin();
        $this->postJson("/api/license/{$license->getId()}/resend-link", []);
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
