<?php

namespace App\Tests\Functional;

use App\Entity\MemberDocument;
use App\Tests\Support\Fake\FakeBunnyStorageClient;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * /api/member/{id}/media — médiathèque par membre, ROLE_SUPER_ADMIN uniquement.
 */
class MemberMediaApiTest extends ApiTestCase
{
    // ── Accès ────────────────────────────────────────────────────────────────

    public function testUnauthenticatedIsRejected(): void
    {
        $member = $this->aMember()->persist();
        $this->getJson('/api/member/'.$member->getId().'/media');

        $this->assertJsonResponse(401);
    }

    public function testAdminIsForbidden(): void
    {
        $member = $this->aMember()->persist();
        $this->actingAsAdmin();

        $this->getJson('/api/member/'.$member->getId().'/media');
        $this->assertJsonResponse(403);

        $this->postJson('/api/member/'.$member->getId().'/media/folder', ['name' => 'X']);
        $this->assertJsonResponse(403);
    }

    // ── GET : seeding du mapping par défaut ──────────────────────────────────

    public function testGetSeedsCurrentSeasonWithDefaultSlots(): void
    {
        $member = $this->aMember()->persist();
        $this->actingAsSuperAdmin();

        $tree = $this->tree($member->getId());

        // Deux dossiers racine : Identité (hors saison) + saison courante.
        $this->assertCount(2, $tree);

        $season = $this->seasonFolder($tree);
        $this->assertSame('folder', $season['type']);
        $this->assertTrue($season['protected']);
        $this->assertNotNull($season['season']);
        $seasonKeys = array_column($season['children'], 'systemKey');
        sort($seasonKeys);
        $this->assertSame(['attestation', 'license', 'medical_certificate'], $seasonKeys);

        $identity = $this->rootFolder($tree, 'identity');
        $this->assertTrue($identity['protected']);
        $this->assertNull($identity['season'], 'Le dossier Identité est indépendant de la saison');
        $identityKeys = array_column($identity['children'], 'systemKey');
        sort($identityKeys);
        $this->assertSame(['id_card', 'identity_photo'], $identityKeys);

        // Tous les slots par défaut sont protégés et vides au départ.
        foreach ([...$season['children'], ...$identity['children']] as $slot) {
            $this->assertSame('document', $slot['type']);
            $this->assertTrue($slot['protected']);
            $this->assertFalse($slot['hasFile']);
        }
    }

    public function testGetIsIdempotent(): void
    {
        $member = $this->aMember()->persist();
        $this->actingAsSuperAdmin();

        $this->tree($member->getId());
        $this->tree($member->getId());

        // Identité (1 dossier + 2 docs) + saison (1 dossier + 3 slots), sans doublon.
        $this->assertSame(7, $this->em()->getRepository(MemberDocument::class)->count([]));
    }

    public function testGetUnknownMemberReturns404(): void
    {
        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/999999/media');

        $body = $this->assertJsonResponse(404);
        $this->assertSame('Member not found', $body['message']);
    }

    // ── Dossiers ─────────────────────────────────────────────────────────────

    public function testCreateFolderAtRootThenNested(): void
    {
        $member = $this->aMember()->persist();
        $this->actingAsSuperAdmin();
        $season = $this->seasonFolder($this->tree($member->getId()));

        // À la racine
        $this->postJson('/api/member/'.$member->getId().'/media/folder', ['name' => 'Divers']);
        $folder = $this->assertJsonResponse(200);
        $this->assertSame('folder', $folder['type']);
        $this->assertSame('Divers', $folder['name']);
        $this->assertNull($folder['parentId']);
        $this->assertFalse($folder['protected']);

        // Sous un parent
        $this->postJson('/api/member/'.$member->getId().'/media/folder', [
            'name' => 'Sous-dossier',
            'parentId' => $folder['id'],
        ]);
        $nested = $this->assertJsonResponse(200);
        $this->assertSame($folder['id'], $nested['parentId']);

        // La racine compte désormais Identité + saison + le dossier libre
        $this->assertCount(3, $this->tree($member->getId()));
        $this->assertNotNull($season['id']);
    }

    public function testCreateFolderWithBlankNameIsRejected(): void
    {
        $member = $this->aMember()->persist();
        $this->actingAsSuperAdmin();

        $this->postJson('/api/member/'.$member->getId().'/media/folder', ['name' => '']);
        $this->assertJsonResponse(422);
    }

    public function testCreateFolderUnderUnknownParentReturns404(): void
    {
        $member = $this->aMember()->persist();
        $this->actingAsSuperAdmin();

        $this->postJson('/api/member/'.$member->getId().'/media/folder', [
            'name' => 'X',
            'parentId' => '00000000-0000-0000-0000-000000000000',
        ]);
        $body = $this->assertJsonResponse(404);
        $this->assertSame('Parent not found', $body['message']);
    }

    public function testCreateFolderUnderADocumentIsRejected(): void
    {
        $member = $this->aMember()->persist();
        $this->actingAsSuperAdmin();
        $slot = $this->slot($this->tree($member->getId()), 'license');

        $this->postJson('/api/member/'.$member->getId().'/media/folder', [
            'name' => 'X',
            'parentId' => $slot['id'],
        ]);
        $body = $this->assertJsonResponse(400);
        $this->assertSame('Le parent doit être un dossier', $body['message']);
    }

    public function testCreateFolderUnknownMemberReturns404(): void
    {
        $this->actingAsSuperAdmin();
        $this->postJson('/api/member/999999/media/folder', ['name' => 'X']);
        $this->assertJsonResponse(404);
    }

    // ── Documents (création avec fichier) ────────────────────────────────────

    public function testCreateDocumentWithFile(): void
    {
        $member = $this->aMember()->persist();
        $this->actingAsSuperAdmin();
        $season = $this->seasonFolder($this->tree($member->getId()));

        $this->uploadWithFields(
            '/api/member/'.$member->getId().'/media/document',
            $this->fakePdf('attestation.pdf'),
            ['name' => 'Attestation', 'parentId' => $season['id']],
        );
        $doc = $this->assertJsonResponse(200);
        $this->assertSame('document', $doc['type']);
        $this->assertSame('Attestation', $doc['name']);
        $this->assertTrue($doc['hasFile']);
        $this->assertSame('attestation.pdf', $doc['originalName']);
        $this->assertSame($season['id'], $doc['parentId']);
    }

    public function testCreateDocumentWithBlankNameIsRejected(): void
    {
        $member = $this->aMember()->persist();
        $this->actingAsSuperAdmin();

        $this->uploadWithFields(
            '/api/member/'.$member->getId().'/media/document',
            $this->fakePdf(),
            ['name' => '  '],
        );
        $body = $this->assertJsonResponse(400);
        $this->assertSame('Le nom du document est requis', $body['message']);
    }

    public function testCreateDocumentUnderADocumentIsRejected(): void
    {
        $member = $this->aMember()->persist();
        $this->actingAsSuperAdmin();
        $slot = $this->slot($this->tree($member->getId()), 'license');

        $this->uploadWithFields(
            '/api/member/'.$member->getId().'/media/document',
            $this->fakePdf(),
            ['name' => 'X', 'parentId' => $slot['id']],
        );
        $this->assertJsonResponse(400);
    }

    public function testCreateDocumentUnknownMemberReturns404(): void
    {
        $this->actingAsSuperAdmin();
        $this->uploadWithFields('/api/member/999999/media/document', $this->fakePdf(), ['name' => 'X']);
        $this->assertJsonResponse(404);
    }

    // ── Fichier sur un slot existant : upload / download / delete ────────────

    public function testFillDefaultSlotDownloadThenClear(): void
    {
        $member = $this->aMember()->persist();
        $this->actingAsSuperAdmin();
        $slot = $this->slot($this->tree($member->getId()), 'license');

        // Upload dans le slot licence
        $this->uploadFile('/api/member/'.$member->getId().'/media/node/'.$slot['id'].'/file', $this->fakePdf());
        $filled = $this->assertJsonResponse(200);
        $this->assertTrue($filled['hasFile']);

        // Download
        $this->getJson('/api/member/'.$member->getId().'/media/node/'.$slot['id'].'/download');
        $this->assertSame(200, $this->response()->getStatusCode());

        // Suppression du fichier : le slot reste, vide
        $this->deleteJson('/api/member/'.$member->getId().'/media/node/'.$slot['id'].'/file');
        $cleared = $this->assertJsonResponse(200);
        $this->assertFalse($cleared['hasFile']);

        $this->getJson('/api/member/'.$member->getId().'/media/node/'.$slot['id'].'/download');
        $this->assertJsonResponse(404);
    }

    public function testDownloadOfAnAccentedFilename(): void
    {
        $member = $this->aMember()->persist();
        $this->actingAsSuperAdmin();
        $slot = $this->slot($this->tree($member->getId()), 'license');

        $this->uploadFile(
            '/api/member/'.$member->getId().'/media/node/'.$slot['id'].'/file',
            $this->fakePdf('Certificat médical 100%.pdf'),
        );
        $this->assertJsonResponse(200);

        $this->getJson('/api/member/'.$member->getId().'/media/node/'.$slot['id'].'/download');
        $this->assertSame(200, $this->response()->getStatusCode());

        // Le nom réel passe en UTF-8 dans `filename*`, le repli ASCII substitue
        // accents et « % » — sans lui, makeDisposition() jette une 500. La
        // substitution est faite octet par octet, d'où deux « _ » pour le « é ».
        $disposition = (string) $this->response()->headers->get('Content-Disposition');
        $this->assertStringContainsString('filename="Certificat m__dical 100_.pdf"', $disposition);
        $this->assertStringContainsString("filename*=utf-8''", $disposition);
    }

    public function testReuploadReplacesTheOldFileOnDisk(): void
    {
        $member = $this->aMember()->persist();
        $this->actingAsSuperAdmin();
        $slot = $this->slot($this->tree($member->getId()), 'license');
        $uri = '/api/member/'.$member->getId().'/media/node/'.$slot['id'].'/file';

        $this->uploadFile($uri, $this->fakePdf('a.pdf'));
        $this->assertJsonResponse(200);
        $first = $this->storedNameOf($member->getId(), $slot['id']);

        $this->uploadFile($uri, $this->fakePdf('b.pdf'));
        $this->assertJsonResponse(200);
        $second = $this->storedNameOf($member->getId(), $slot['id']);

        $this->assertNotSame($first, $second);
        $bunny = static::getContainer()->get(FakeBunnyStorageClient::class);
        $this->assertFalse($bunny->has($first), 'Le fichier remplacé doit disparaître du stockage');
        $this->assertTrue($bunny->has($second));
    }

    public function testAFailedReplacementKeepsThePreviousFile(): void
    {
        $member = $this->aMember()->persist();
        $this->actingAsSuperAdmin();
        $slot = $this->slot($this->tree($member->getId()), 'license');
        $uri = '/api/member/'.$member->getId().'/media/node/'.$slot['id'].'/file';

        $this->uploadFile($uri, $this->fakePdf('a.pdf'));
        $this->assertJsonResponse(200);
        $first = $this->storedNameOf($member->getId(), $slot['id']);

        // Le PUT du remplaçant échoue : sans suppression anticipée, la base doit
        // toujours pointer sur un fichier qui existe encore dans la zone.
        FakeBunnyStorageClient::failPutsFromCall(2);
        $this->uploadFile($uri, $this->fakePdf('b.pdf'));
        $this->assertJsonResponse(502);

        $bunny = static::getContainer()->get(FakeBunnyStorageClient::class);
        $this->assertTrue($bunny->has($first), "L'ancien fichier doit survivre à un store en échec");
        $this->assertSame($first, $this->storedNameOf($member->getId(), $slot['id']));
    }

    public function testUploadFileOnAFolderIsRejected(): void
    {
        $member = $this->aMember()->persist();
        $this->actingAsSuperAdmin();
        $season = $this->seasonFolder($this->tree($member->getId()));

        $this->uploadFile('/api/member/'.$member->getId().'/media/node/'.$season['id'].'/file', $this->fakePdf());
        $body = $this->assertJsonResponse(400);
        $this->assertSame('Un dossier ne peut pas porter de fichier', $body['message']);
    }

    public function testUploadFileOnUnknownNodeReturns404(): void
    {
        $member = $this->aMember()->persist();
        $this->actingAsSuperAdmin();

        $this->uploadFile('/api/member/'.$member->getId().'/media/node/999999/file', $this->fakePdf());
        $this->assertJsonResponse(404);
    }

    public function testCannotReachAnotherMembersNode(): void
    {
        $alice = $this->aMember()->persist();
        $bob = $this->aMember()->persist();
        $this->actingAsSuperAdmin();
        $bobSlot = $this->slot($this->tree($bob->getId()), 'license');

        // Le slot de Bob, adressé sous Alice : introuvable
        $this->uploadFile('/api/member/'.$alice->getId().'/media/node/'.$bobSlot['id'].'/file', $this->fakePdf());
        $this->assertJsonResponse(404);
    }

    public function testDeleteFileOnFolderIsRejected(): void
    {
        $member = $this->aMember()->persist();
        $this->actingAsSuperAdmin();
        $season = $this->seasonFolder($this->tree($member->getId()));

        $this->deleteJson('/api/member/'.$member->getId().'/media/node/'.$season['id'].'/file');
        $this->assertJsonResponse(400);
    }

    // ── Renommage ────────────────────────────────────────────────────────────

    public function testRenameFreeFolder(): void
    {
        $member = $this->aMember()->persist();
        $this->actingAsSuperAdmin();
        $this->tree($member->getId());

        $this->postJson('/api/member/'.$member->getId().'/media/folder', ['name' => 'Old']);
        $folder = $this->assertJsonResponse(200);

        $this->patchJson('/api/member/'.$member->getId().'/media/node/'.$folder['id'], ['name' => 'New']);
        $renamed = $this->assertJsonResponse(200);
        $this->assertSame('New', $renamed['name']);
    }

    public function testRenameProtectedNodeIsRejected(): void
    {
        $member = $this->aMember()->persist();
        $this->actingAsSuperAdmin();
        $season = $this->seasonFolder($this->tree($member->getId()));

        $this->patchJson('/api/member/'.$member->getId().'/media/node/'.$season['id'], ['name' => 'Nope']);
        $body = $this->assertJsonResponse(400);
        $this->assertSame('Cet élément par défaut ne peut pas être renommé', $body['message']);
    }

    public function testRenameWithBlankNameIsRejected(): void
    {
        $member = $this->aMember()->persist();
        $this->actingAsSuperAdmin();
        $this->postJson('/api/member/'.$member->getId().'/media/folder', ['name' => 'Old']);
        $folder = $this->assertJsonResponse(200);

        $this->patchJson('/api/member/'.$member->getId().'/media/node/'.$folder['id'], ['name' => '']);
        $this->assertJsonResponse(422);
    }

    public function testRenameUnknownNodeReturns404(): void
    {
        $member = $this->aMember()->persist();
        $this->actingAsSuperAdmin();

        $this->patchJson('/api/member/'.$member->getId().'/media/node/999999', ['name' => 'X']);
        $this->assertJsonResponse(404);
    }

    // ── Suppression de nœud ──────────────────────────────────────────────────

    public function testDeleteFolderCascadesAndRemovesFiles(): void
    {
        $member = $this->aMember()->persist();
        $this->actingAsSuperAdmin();
        $this->tree($member->getId());

        // Dossier libre contenant un document avec fichier
        $this->postJson('/api/member/'.$member->getId().'/media/folder', ['name' => 'Boîte']);
        $folder = $this->assertJsonResponse(200);
        $this->uploadWithFields(
            '/api/member/'.$member->getId().'/media/document',
            $this->fakePdf(),
            ['name' => 'Pièce', 'parentId' => $folder['id']],
        );
        $doc = $this->assertJsonResponse(200);
        $stored = $this->storedNameOf($member->getId(), $doc['id']);
        $bunny = static::getContainer()->get(FakeBunnyStorageClient::class);
        $this->assertTrue($bunny->has($stored));
        // Un dossier par membre dans la zone.
        $this->assertStringStartsWith($member->getId().'/', $stored);

        $this->deleteJson('/api/member/'.$member->getId().'/media/node/'.$folder['id']);
        $body = $this->assertJsonResponse(200);
        $this->assertTrue($body['deleted']);
        $this->assertSame($folder['id'], $body['id']);

        // Le dossier et son document ont disparu, fichier effacé du disque.
        $repo = $this->em()->getRepository(MemberDocument::class);
        $this->assertNull($repo->findOneBy(['uuid' => $folder['id']]));
        $this->assertNull($repo->findOneBy(['uuid' => $doc['id']]));
        $this->assertFalse($bunny->has($stored));
    }

    public function testDeleteProtectedNodeIsRejected(): void
    {
        $member = $this->aMember()->persist();
        $this->actingAsSuperAdmin();
        $slot = $this->slot($this->tree($member->getId()), 'medical_certificate');

        $this->deleteJson('/api/member/'.$member->getId().'/media/node/'.$slot['id']);
        $body = $this->assertJsonResponse(400);
        $this->assertSame('Cet élément par défaut ne peut pas être supprimé', $body['message']);
    }

    public function testDeleteUnknownNodeReturns404(): void
    {
        $member = $this->aMember()->persist();
        $this->actingAsSuperAdmin();

        $this->deleteJson('/api/member/'.$member->getId().'/media/node/999999');
        $this->assertJsonResponse(404);
    }

    public function testDownloadUnknownMemberReturns404(): void
    {
        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/999999/media/node/whatever/download');
        $this->assertJsonResponse(404);
    }

    public function testNodeRoutesOnUnknownMemberReturn404(): void
    {
        $this->actingAsSuperAdmin();
        $base = '/api/member/999999/media/node/whatever';

        $this->uploadFile($base.'/file', $this->fakePdf());
        $this->assertJsonResponse(404);

        $this->deleteJson($base.'/file');
        $this->assertJsonResponse(404);

        $this->patchJson($base, ['name' => 'X']);
        $this->assertJsonResponse(404);

        $this->deleteJson($base);
        $this->assertJsonResponse(404);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** GET la médiathèque et renvoie l'arbre décodé (200 attendu). */
    private function tree(int $memberId): array
    {
        $this->getJson('/api/member/'.$memberId.'/media');

        return $this->assertJsonResponse(200);
    }

    /** Dossier racine de saison (systemKey "season"). */
    private function seasonFolder(array $tree): array
    {
        return $this->rootFolder($tree, 'season');
    }

    /** Dossier racine par sa clé système (season, identity, …). */
    private function rootFolder(array $tree, string $systemKey): array
    {
        foreach ($tree as $node) {
            if ($node['systemKey'] === $systemKey) {
                return $node;
            }
        }

        $this->fail(sprintf('Dossier racine "%s" introuvable', $systemKey));
    }

    /** Récupère un slot par défaut par sa clé système, où qu'il soit dans l'arbre. */
    private function slot(array $tree, string $systemKey): array
    {
        foreach ($tree as $root) {
            foreach ($root['children'] as $child) {
                if ($child['systemKey'] === $systemKey) {
                    return $child;
                }
            }
        }

        $this->fail(sprintf('Slot "%s" introuvable', $systemKey));
    }

    private function storedNameOf(int $memberId, string $uuid): string
    {
        $this->em()->clear();
        $node = $this->em()->getRepository(MemberDocument::class)->findOneBy(['uuid' => $uuid]);
        $this->assertNotNull($node);

        return (string) $node->getStoredName();
    }

    /** POST multipart avec champs de formulaire + un fichier. */
    private function uploadWithFields(string $uri, UploadedFile $file, array $fields, string $key = 'file'): void
    {
        $this->client->request(
            'POST',
            $uri,
            parameters: $fields,
            files: [$key => $file],
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
    }
}
