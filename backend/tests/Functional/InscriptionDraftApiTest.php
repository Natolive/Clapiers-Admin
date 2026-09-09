<?php

namespace App\Tests\Functional;

use App\Common\Service\InscriptionsStatusProvider;
use App\Entity\InscriptionDraft;
use App\Entity\License;
use App\Entity\MemberDocument;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Fake\FakeBunnyStorageClient;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Brouillon d'inscription publique : `/api/public/inscription-draft`. Routes
 * publiques (PUBLIC_ACCESS), autorisées par le seul token du brouillon ; le
 * captcha est désactivé en test (secret vide), son refus est couvert par
 * CreateInscriptionDraftUseCaseTest.
 *
 * L'enjeu de ces tests : une pièce déposée sur le brouillon est réellement
 * reçue avant que la demande existe, et la validation la retrouve dans le bon
 * slot médiathèque. C'est ce qui remplace la rafale d'uploads d'après-coup, qui
 * laissait des demandes sans leurs pièces dès qu'un envoi échouait.
 */
class InscriptionDraftApiTest extends ApiTestCase
{
    private const UNKNOWN_TOKEN = 'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff';

    // ── Ouverture ───────────────────────────────────────────────────────────

    public function testCreateReturnsAnEmptyDraftWithItsToken(): void
    {
        $this->postJson('/api/public/inscription-draft', ['recaptchaToken' => 'test-token']);

        $body = $this->assertJsonResponse(200);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $body['token']);
        $this->assertSame([], $body['payload']);
        $this->assertSame([], $body['documents']);
    }

    public function testCreateIsRefusedWhenTheFormIsClosed(): void
    {
        static::getContainer()->get(InscriptionsStatusProvider::class)->setFormOpen(false);

        $this->postJson('/api/public/inscription-draft', ['recaptchaToken' => 'test-token']);

        $this->assertJsonResponse(403);
        $this->assertCount(0, $this->draftRepo()->findAll());
    }

    public function testCreateWithoutACaptchaTokenIsRejected(): void
    {
        $this->postJson('/api/public/inscription-draft', []);

        $this->assertJsonResponse(422);
    }

    // ── Sauvegarde et reprise ───────────────────────────────────────────────

    public function testSavedPayloadIsRestoredByToken(): void
    {
        $token = $this->openDraft();

        $this->putJson('/api/public/inscription-draft/'.$token, ['payload' => [
            'firstName' => 'Marie',
            'birthDate' => '2000-05-01',
            'healthDeclaration' => true,
        ]]);
        $this->assertJsonResponse(200);

        $this->getJson('/api/public/inscription-draft/'.$token);

        $body = $this->assertJsonResponse(200);
        $this->assertSame('Marie', $body['payload']['firstName']);
        $this->assertSame('2000-05-01', $body['payload']['birthDate']);
        $this->assertTrue($body['payload']['healthDeclaration']);
    }

    /**
     * Le brouillon est écrit par un appel public non authentifié : il n'accepte
     * que des scalaires, bornés en nombre et en longueur. Il ne sert qu'à
     * réafficher le formulaire, la validation restant portée par la soumission.
     */
    public function testSavedPayloadKeepsOnlyBoundedScalarFields(): void
    {
        $token = $this->openDraft();

        $this->putJson('/api/public/inscription-draft/'.$token, ['payload' => array_merge(
            ['nested' => ['a' => 'b'], 'long' => str_repeat('x', 900), 'ok' => 'gardé'],
            array_combine(
                array_map(static fn (int $i) => 'f'.$i, range(1, 60)),
                array_fill(0, 60, 'v'),
            ),
        )]);
        $this->assertJsonResponse(200);

        $payload = $this->draftRepo()->findOneByToken($token)->getPayload();
        $this->assertArrayNotHasKey('nested', $payload, 'Une valeur non scalaire ne doit pas être stockée');
        $this->assertSame(500, mb_strlen($payload['long']));
        $this->assertSame('gardé', $payload['ok']);
        $this->assertCount(40, $payload);
    }

    public function testSavingAnUnknownDraftIs404(): void
    {
        $this->putJson('/api/public/inscription-draft/'.self::UNKNOWN_TOKEN, ['payload' => ['firstName' => 'X']]);

        $this->assertJsonResponse(404);
    }

    public function testAnInvalidJsonBodyDoesNotBreakTheSave(): void
    {
        $token = $this->openDraft();

        $this->client->request(
            'PUT',
            '/api/public/inscription-draft/'.$token,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: '{pas du json',
        );

        $body = $this->assertJsonResponse(200);
        $this->assertSame([], $body['payload']);
    }

    public function testGettingAnUnknownDraftIs404(): void
    {
        $this->getJson('/api/public/inscription-draft/'.self::UNKNOWN_TOKEN);

        $this->assertJsonResponse(404);
    }

    /** Un token mal formé est écarté par le routeur, sans toucher la base. */
    public function testAMalformedTokenIsNotEvenRouted(): void
    {
        $this->getJson('/api/public/inscription-draft/pas-un-token');

        $this->assertSame(404, $this->response()->getStatusCode());
    }

    // ── Dépôt des pièces ────────────────────────────────────────────────────

    public function testUploadStoresTheFileInTheDraftFolder(): void
    {
        $token = $this->openDraft();

        $this->uploadFile('/api/public/inscription-draft/'.$token.'/document/identity_photo', $this->fakePng());

        $body = $this->assertJsonResponse(200);
        $this->assertSame('photo.png', $body['documents']['identity_photo']['originalName']);
        $this->assertSame('image/png', $body['documents']['identity_photo']['mimeType']);
        $this->assertArrayNotHasKey('storedName', $body['documents']['identity_photo'], "Le chemin de stockage n'a pas à sortir");

        $stored = $this->storedName($token, 'identity_photo');
        $this->assertStringStartsWith('drafts/'.$token.'/', $stored);
        $this->assertTrue($this->bunny()->has($stored));
    }

    public function testUploadingAgainReplacesThePieceAndDropsTheOldFile(): void
    {
        $token = $this->openDraft();

        $this->uploadFile('/api/public/inscription-draft/'.$token.'/document/id_card', $this->fakePdf('recto.pdf'));
        $this->assertJsonResponse(200);
        $first = $this->storedName($token, 'id_card');

        $this->uploadFile('/api/public/inscription-draft/'.$token.'/document/id_card', $this->fakePdf('recto-verso.pdf'));
        $body = $this->assertJsonResponse(200);
        $second = $this->storedName($token, 'id_card');

        $this->assertSame('recto-verso.pdf', $body['documents']['id_card']['originalName']);
        $this->assertNotSame($first, $second);
        $this->assertFalse($this->bunny()->has($first), 'Le fichier remplacé doit disparaître de la zone');
        $this->assertTrue($this->bunny()->has($second));
    }

    public function testUploadRejectsAFileAboveTheRouteLimit(): void
    {
        $token = $this->openDraft();

        $this->uploadFile(
            '/api/public/inscription-draft/'.$token.'/document/medical_certificate',
            $this->fakePdfOfSize(6 * 1024 * 1024 + 1024),
        );

        $this->assertJsonResponse(422);
        $this->assertSame([], $this->draftRepo()->findOneByToken($token)->getDocuments());
    }

    public function testUploadRejectsADisallowedMimeType(): void
    {
        $token = $this->openDraft();

        $this->uploadFile('/api/public/inscription-draft/'.$token.'/document/medical_certificate', $this->fakeCsv('a,b,c'));

        $this->assertJsonResponse(422);
    }

    public function testUploadRejectsAPdfAsIdentityPhoto(): void
    {
        $token = $this->openDraft();

        $this->uploadFile('/api/public/inscription-draft/'.$token.'/document/identity_photo', $this->fakePdf());

        $body = $this->assertJsonResponse(422);
        $this->assertSame('La photo de profil doit être une image.', $body['message']);
    }

    public function testUploadOnAnUnknownDraftIs404(): void
    {
        $this->uploadFile('/api/public/inscription-draft/'.self::UNKNOWN_TOKEN.'/document/id_card', $this->fakePdf());

        $this->assertJsonResponse(404);
    }

    public function testDeletingAPieceRemovesItsFile(): void
    {
        $token = $this->openDraft();
        $this->uploadFile('/api/public/inscription-draft/'.$token.'/document/attestation', $this->fakePdf());
        $this->assertJsonResponse(200);
        $stored = $this->storedName($token, 'attestation');

        $this->deleteJson('/api/public/inscription-draft/'.$token.'/document/attestation');

        $body = $this->assertJsonResponse(200);
        $this->assertSame([], $body['documents']);
        $this->assertFalse($this->bunny()->has($stored));
    }

    /** Retirer une pièce absente n'est pas une erreur. */
    public function testDeletingAMissingPieceIsAccepted(): void
    {
        $token = $this->openDraft();

        $this->deleteJson('/api/public/inscription-draft/'.$token.'/document/attestation');

        $this->assertJsonResponse(200);
    }

    public function testDeletingAPieceOfAnUnknownDraftIs404(): void
    {
        $this->deleteJson('/api/public/inscription-draft/'.self::UNKNOWN_TOKEN.'/document/id_card');

        $this->assertJsonResponse(404);
    }

    public function testDeletingTheDraftRemovesTheRowAndItsFiles(): void
    {
        $token = $this->openDraft();
        $this->uploadFile('/api/public/inscription-draft/'.$token.'/document/id_card', $this->fakePdf());
        $this->assertJsonResponse(200);
        $stored = $this->storedName($token, 'id_card');

        $this->deleteJson('/api/public/inscription-draft/'.$token);

        $body = $this->assertJsonResponse(200);
        $this->assertTrue($body['deleted']);
        $this->assertNull($this->draftRepo()->findOneByToken($token));
        $this->assertFalse($this->bunny()->has($stored));
    }

    public function testDeletingAnUnknownDraftIs404(): void
    {
        $this->deleteJson('/api/public/inscription-draft/'.self::UNKNOWN_TOKEN);

        $this->assertJsonResponse(404);
    }

    // ── Validation : rattachement des pièces ────────────────────────────────

    public function testSubmitAttachesEveryDraftPieceToTheRightSlotAndDropsTheDraft(): void
    {
        $token = $this->openDraft();
        foreach (['identity_photo' => $this->fakePng(), 'id_card' => $this->fakePdf('cni.pdf'),
            'medical_certificate' => $this->fakePdf('certif.pdf'), 'attestation' => $this->fakePdf('attest.pdf')] as $key => $file) {
            $this->uploadFile('/api/public/inscription-draft/'.$token.'/document/'.$key, $file);
            $this->assertJsonResponse(200);
        }
        $draftFiles = $this->draftRepo()->findOneByToken($token)->storedNames();

        // Pas de `recaptchaToken` : le captcha a été validé à l'ouverture du
        // brouillon, et son token en tient lieu.
        $this->postJson('/api/public/license-request', $this->validPayload(['draftToken' => $token]));

        $body = $this->assertJsonResponse(200);
        $license = $this->em()->getRepository(License::class)->find($body['id']);
        $member = $license->getMember();
        $season = $license->getSeason();

        // identity_photo / id_card → dossier racine « Identité ».
        foreach (['identity_photo' => 'photo.png', 'id_card' => 'cni.pdf'] as $key => $originalName) {
            $slot = $this->documentRepo()->findRootDocumentSlot($member, $key);
            $this->assertNotNull($slot, sprintf('Slot racine %s attendu', $key));
            $this->assertTrue($slot->hasFile());
            $this->assertSame($originalName, $slot->getOriginalName());
            $this->assertStringStartsWith($member->getId().'/', $slot->getStoredName());
            $this->assertTrue($this->bunny()->has($slot->getStoredName()));
        }

        // certificat / attestation → dossier de la saison de la licence.
        foreach (['medical_certificate' => 'certif.pdf', 'attestation' => 'attest.pdf'] as $key => $originalName) {
            $slot = $this->documentRepo()->findDefaultSlot($member, $season, $key);
            $this->assertNotNull($slot, sprintf('Slot de saison %s attendu', $key));
            $this->assertTrue($slot->hasFile());
            $this->assertSame($originalName, $slot->getOriginalName());
            $this->assertTrue($this->bunny()->has($slot->getStoredName()));
        }

        // Marqueur du badge « certificat déposé » côté admin.
        $this->assertNotNull($license->getMedicalCertificateFileName());

        // Le brouillon et ses fichiers ont disparu.
        $this->assertNull($this->draftRepo()->findOneByToken($token));
        foreach ($draftFiles as $draftFile) {
            $this->assertFalse($this->bunny()->has($draftFile), 'Les objets du brouillon doivent être nettoyés');
        }
    }

    public function testSubmitWithAnUnknownDraftTokenCreatesNothing(): void
    {
        $this->postJson('/api/public/license-request', $this->validPayload(['draftToken' => self::UNKNOWN_TOKEN]));

        $this->assertJsonResponse(404);
        $this->assertCount(0, $this->em()->getRepository(License::class)->findAll());
    }

    /** Sans brouillon, le captcha reste exigé et la demande part sans pièces. */
    public function testSubmitStillWorksWithoutADraft(): void
    {
        $this->postJson('/api/public/license-request', $this->validPayload());

        $this->assertJsonResponse(200);
    }

    // ── Purge ───────────────────────────────────────────────────────────────

    public function testOpeningADraftPurgesTheOnesInactiveForAMonth(): void
    {
        $stale = $this->openDraft();
        $this->uploadFile('/api/public/inscription-draft/'.$stale.'/document/id_card', $this->fakePdf());
        $this->assertJsonResponse(200);
        $staleFile = $this->storedName($stale, 'id_card');
        $this->touchDraft($stale, '-31 days');

        $recent = $this->openDraft();
        $this->touchDraft($recent, '-29 days');

        $this->openDraft();

        $this->assertNull($this->draftRepo()->findOneByToken($stale));
        $this->assertFalse($this->bunny()->has($staleFile), 'Un brouillon purgé emporte ses fichiers');
        $this->assertNotNull($this->draftRepo()->findOneByToken($recent));
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function openDraft(): string
    {
        $this->postJson('/api/public/inscription-draft', ['recaptchaToken' => 'test-token']);

        return $this->assertJsonResponse(200)['token'];
    }

    /** Le noyau reboote entre deux requêtes : toujours relire par le repository. */
    private function storedName(string $token, string $systemKey): string
    {
        return $this->draftRepo()->findOneByToken($token)->getDocuments()[$systemKey]['storedName'];
    }

    /** Vieillit un brouillon (aucun setter : `updatedAt` n'est piloté que par l'entité). */
    private function touchDraft(string $token, string $modifier): void
    {
        $this->em()->getConnection()->executeStatement(
            'UPDATE inscription_draft SET updated_at = :when WHERE token = :token',
            ['when' => (new \DateTimeImmutable('now'))->modify($modifier)->format('Y-m-d H:i:s'), 'token' => $token],
        );
    }

    private function draftRepo(): \App\Repository\InscriptionDraftRepository
    {
        return $this->em()->getRepository(InscriptionDraft::class);
    }

    private function documentRepo(): \App\Repository\MemberDocumentRepository
    {
        return $this->em()->getRepository(MemberDocument::class);
    }

    private function bunny(): FakeBunnyStorageClient
    {
        return static::getContainer()->get(FakeBunnyStorageClient::class);
    }

    /** PDF valide (pour la détection de type) complété jusqu'à la taille voulue. */
    private function fakePdfOfSize(int $bytes): UploadedFile
    {
        $header = "%PDF-1.4\n";
        $path = tempnam(sys_get_temp_dir(), 'test_pdf_size_');
        file_put_contents($path, $header.str_repeat('x', $bytes - \strlen($header)));

        return new UploadedFile($path, 'gros.pdf', 'application/pdf', test: true);
    }

    /** @param array<string, mixed> $overrides */
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
}
