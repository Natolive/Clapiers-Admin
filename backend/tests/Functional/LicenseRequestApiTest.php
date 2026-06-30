<?php

namespace App\Tests\Functional;

use App\Entity\Enum\LicenseStatus;
use App\Entity\Enum\MemberStatus;
use App\Entity\License;
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

    public function testUploadMedicalCertificateStoresTheFile(): void
    {
        $this->aLicense()->withToken('tok-upload')->persist();

        $this->uploadFile('/api/public/license-request/tok-upload/medical-certificate', $this->fakePdf());

        $body = $this->assertJsonResponse(200);
        $this->assertNotNull($body['medicalCertificateFileName']);
        $this->assertStringEndsWith('.pdf', $body['medicalCertificateFileName']);
    }

    public function testUploadingTwiceReplacesThePreviousFile(): void
    {
        $this->aLicense()->withToken('tok-twice')->persist();

        $this->uploadFile('/api/public/license-request/tok-twice/medical-certificate', $this->fakePdf());
        $first = $this->assertJsonResponse(200)['medicalCertificateFileName'];

        $this->uploadFile('/api/public/license-request/tok-twice/medical-certificate', $this->fakePdf());
        $second = $this->assertJsonResponse(200)['medicalCertificateFileName'];

        $this->assertNotSame($first, $second);
    }

    public function testUploadWithUnknownTokenReturns404(): void
    {
        $this->uploadFile('/api/public/license-request/does-not-exist/medical-certificate', $this->fakePdf());

        $this->assertJsonResponse(404);
    }

    public function testUploadRejectsDisallowedMimeType(): void
    {
        $this->aLicense()->withToken('tok-mime')->persist();

        $this->uploadFile('/api/public/license-request/tok-mime/medical-certificate', $this->fakeCsv('a,b,c'));

        $this->assertJsonResponse(422);
    }
}
