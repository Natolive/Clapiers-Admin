<?php

namespace App\Tests\Functional;

use App\Entity\Enum\LicenseStatus;
use App\Entity\Enum\MemberStatus;
use App\Entity\License;
use App\Entity\MemberDocument;
use App\Tests\Support\ApiTestCase;

/**
 * Demande de licence publique : POST /api/public/license-request (+ upload du
 * certificat médical). Routes publiques (PUBLIC_ACCESS), captcha bypassé en test.
 */
class LicenseRequestApiTest extends ApiTestCase
{
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'firstName' => 'Marie',
            'lastName' => 'Curie',
            'phoneNumber' => '+33612345678',
            'email' => 'marie.curie@test.fr',
            'addressStreet' => '1 rue des Sciences',
            'addressZip' => '34000',
            'addressCity' => 'Montpellier',
            'gender' => 'female',
            'birthDate' => '2000-05-01',
            'nationality' => 'Française',
            'recaptchaToken' => 'test-token',
        ], $overrides);
    }

    public function testSubmitCreatesPendingMemberAndSubmittedLicense(): void
    {
        $this->postJson('/api/public/license-request', $this->validPayload());

        $body = $this->assertJsonResponse(200);
        $this->assertSame('soumise', $body['status']);
        $this->assertNotEmpty($body['accessToken']);
        $this->assertSame('Marie', $body['member']['firstName']);
        $this->assertSame('pending_validation', $body['member']['status']);

        $license = $this->em()->getRepository(License::class)->find($body['id']);
        $this->assertNotNull($license);
        $this->assertSame(LicenseStatus::SOUMISE, $license->getStatus());
        $this->assertSame(MemberStatus::PENDING_VALIDATION, $license->getMember()->getStatus());
        $this->assertMatchesRegularExpression('/^\d{4}-\d{4}$/', $license->getSeason());
    }

    public function testSubmitWithInvalidEmailIsRejected(): void
    {
        $this->postJson('/api/public/license-request', $this->validPayload(['email' => 'not-an-email']));

        $this->assertJsonResponse(422);
        $this->assertSame(0, $this->em()->getRepository(License::class)->count([]));
    }

    public function testSubmitWithMissingFieldsIsRejected(): void
    {
        $this->postJson('/api/public/license-request', ['firstName' => 'Marie']);

        $this->assertJsonResponse(422);
    }

    public function testSubmitWithUnknownNationalityIsRejected(): void
    {
        $this->postJson('/api/public/license-request', $this->validPayload(['nationality' => 'Martienne']));

        $this->assertJsonResponse(422);
    }

    public function testSubmitStoresLegalRepresentativeAndHealthDeclaration(): void
    {
        $this->postJson('/api/public/license-request', $this->validPayload([
            'birthDate' => '2015-03-10',
            'healthDeclaration' => false,
            'legalRepFirstName' => 'Pierre',
            'legalRepLastName' => 'Curie',
            'legalRepEmail' => 'pierre.curie@test.fr',
            'legalRepPhone' => '+33698765432',
        ]));

        $body = $this->assertJsonResponse(200);
        $this->assertFalse($body['healthDeclaration']);
        $this->assertSame('Pierre', $body['member']['legalRepresentative']['firstName']);
        $this->assertSame('pierre.curie@test.fr', $body['member']['legalRepresentative']['email']);
    }

    public function testUploadMedicalCertificateLandsInTheLicenseSeasonSlot(): void
    {
        $license = $this->aLicense()->withToken('tok-cert')->inSeason('2030-2031')->persist();

        $this->uploadFile('/api/public/license-request/tok-cert/document/medical_certificate', $this->fakePdf());

        $body = $this->assertJsonResponse(200);
        // Marqueur sur la licence (badge admin).
        $this->assertStringEndsWith('.pdf', $body['medicalCertificateFileName']);
        // Le fichier atterrit bien dans le slot médiathèque de LA saison de la licence.
        $slot = $this->documentRepo()->findDefaultSlot($license->getMember(), '2030-2031', 'medical_certificate');
        $this->assertNotNull($slot);
        $this->assertTrue($slot->hasFile());
    }

    public function testUploadAttestationLandsInTheSeasonSlot(): void
    {
        $license = $this->aLicense()->withToken('tok-attest')->inSeason('2030-2031')->persist();

        $this->uploadFile('/api/public/license-request/tok-attest/document/attestation', $this->fakePdf());

        $this->assertJsonResponse(200);
        $slot = $this->documentRepo()->findDefaultSlot($license->getMember(), '2030-2031', 'attestation');
        $this->assertNotNull($slot);
        $this->assertTrue($slot->hasFile());
    }

    public function testUploadProfilePictureAndIdCardLandInIdentityFolder(): void
    {
        $license = $this->aLicense()->withToken('tok-id')->persist();

        $this->uploadFile('/api/public/license-request/tok-id/document/identity_photo', $this->fakePng());
        $this->assertJsonResponse(200);
        $this->uploadFile('/api/public/license-request/tok-id/document/id_card', $this->fakePdf());
        $this->assertJsonResponse(200);

        $member = $license->getMember();
        $this->assertTrue($this->documentRepo()->findRootDocumentSlot($member, 'identity_photo')->hasFile());
        $this->assertTrue($this->documentRepo()->findRootDocumentSlot($member, 'id_card')->hasFile());
    }

    public function testProfilePictureRejectsPdf(): void
    {
        $this->aLicense()->withToken('tok-pp-pdf')->persist();

        $this->uploadFile('/api/public/license-request/tok-pp-pdf/document/identity_photo', $this->fakePdf());

        $this->assertJsonResponse(422);
    }

    public function testUploadingTwiceReplacesThePreviousFile(): void
    {
        $this->aLicense()->withToken('tok-twice')->persist();

        $this->uploadFile('/api/public/license-request/tok-twice/document/medical_certificate', $this->fakePdf());
        $first = $this->assertJsonResponse(200)['medicalCertificateFileName'];

        $this->uploadFile('/api/public/license-request/tok-twice/document/medical_certificate', $this->fakePdf());
        $second = $this->assertJsonResponse(200)['medicalCertificateFileName'];

        $this->assertNotSame($first, $second);
    }

    public function testUploadWithUnknownTokenReturns404(): void
    {
        $this->uploadFile('/api/public/license-request/does-not-exist/document/medical_certificate', $this->fakePdf());

        $this->assertJsonResponse(404);
    }

    public function testUploadWithUnknownSystemKeyReturns404(): void
    {
        $this->aLicense()->withToken('tok-bad-key')->persist();

        $this->uploadFile('/api/public/license-request/tok-bad-key/document/passport', $this->fakePdf());

        $this->assertJsonResponse(404);
    }

    public function testUploadRejectsDisallowedMimeType(): void
    {
        $this->aLicense()->withToken('tok-mime')->persist();

        $this->uploadFile('/api/public/license-request/tok-mime/document/medical_certificate', $this->fakeCsv('a,b,c'));

        $this->assertJsonResponse(422);
    }

    private function documentRepo(): \App\Repository\MemberDocumentRepository
    {
        return $this->em()->getRepository(MemberDocument::class);
    }
}
