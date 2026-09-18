<?php

namespace App\Tests\Functional;

use App\Common\Service\SeasonProvider;
use App\Entity\Enum\LicenseStatus;
use App\Entity\Enum\MemberStatus;
use App\Entity\License;
use App\Entity\Member;
use App\Repository\LicenseRepository;
use App\Tests\Support\ApiTestCase;

/**
 * PUT /api/member/{id}/fsgt — déclaration de l'inscription à la FSGT.
 *
 * L'inscription est portée par la licence de la saison : on est inscrit pour
 * une saison, pas une fois pour toutes. Elle ne se déduit surtout pas du numéro
 * de licence, qu'un renouvellement apporte déjà depuis le formulaire public —
 * ce numéro, lui, est unique et vit sur le membre, d'où qu'on le saisisse.
 */
class MemberFsgtApiTest extends ApiTestCase
{
    // ── Sécurité ────────────────────────────────────────────────────────────

    public function testRequiresAuthentication(): void
    {
        $member = $this->licensedMember();

        $this->putJson('/api/member/'.$member->getId().'/fsgt', ['registered' => true, 'licenseNumber' => 'A1']);

        $this->assertJsonResponse(401);
    }

    public function testIsForbiddenForAdmin(): void
    {
        $member = $this->licensedMember();
        $this->actingAsAdmin();

        $this->putJson('/api/member/'.$member->getId().'/fsgt', ['registered' => true, 'licenseNumber' => 'A1']);

        $this->assertJsonResponse(403);
    }

    // ── Cocher / décocher ───────────────────────────────────────────────────

    public function testRegisteringStampsTheSeasonLicenseAndStoresTheNumberOnTheMember(): void
    {
        $this->actingAsSuperAdmin();
        $member = $this->licensedMember();

        $this->putJson('/api/member/'.$member->getId().'/fsgt', [
            'registered' => true,
            'licenseNumber' => 'FSGT-12345',
        ]);

        $body = $this->assertJsonResponse(200);
        $this->assertTrue($body['fsgtRegistered']);
        $this->assertSame('FSGT-12345', $body['licenseNumber']);
        $this->assertSame($this->currentSeason(), $body['season']);
        $this->assertNotNull($body['fsgtRegisteredAt']);

        $this->assertTrue($this->licenseOf($member, $this->currentSeason())->isFsgtRegistered());
        $this->em()->refresh($member);
        $this->assertSame('FSGT-12345', $member->getLicenseNumber());
    }

    /** Décocher corrige une erreur de saisie : le numéro ne doit pas disparaître. */
    public function testUnregisteringKeepsTheLicenseNumber(): void
    {
        $this->actingAsSuperAdmin();
        $member = $this->licensedMember();
        $this->putJson('/api/member/'.$member->getId().'/fsgt', ['registered' => true, 'licenseNumber' => 'FSGT-1']);
        $this->assertJsonResponse(200);

        $this->putJson('/api/member/'.$member->getId().'/fsgt', ['registered' => false]);

        $body = $this->assertJsonResponse(200);
        $this->assertFalse($body['fsgtRegistered']);
        $this->assertNull($body['fsgtRegisteredAt']);
        $this->assertSame('FSGT-1', $body['licenseNumber']);
    }

    public function testRegisteringWithoutANumberIsRejected(): void
    {
        $this->actingAsSuperAdmin();
        $member = $this->licensedMember();

        $this->putJson('/api/member/'.$member->getId().'/fsgt', ['registered' => true, 'licenseNumber' => '   ']);

        $body = $this->assertJsonResponse(400);
        $this->assertStringContainsString('numéro de licence est requis', $body['message']);
    }

    public function testTooLongNumberIsRejected(): void
    {
        $this->actingAsSuperAdmin();
        $member = $this->licensedMember();

        $this->putJson('/api/member/'.$member->getId().'/fsgt', [
            'registered' => true,
            'licenseNumber' => str_repeat('X', 51),
        ]);

        $this->assertJsonResponse(422);
    }

    public function testInvalidSeasonIsRejected(): void
    {
        $this->actingAsSuperAdmin();
        $member = $this->licensedMember();

        $this->putJson('/api/member/'.$member->getId().'/fsgt', [
            'registered' => true,
            'licenseNumber' => 'A1',
            'season' => '2026',
        ]);

        $this->assertJsonResponse(422);
    }

    public function testUnknownMemberIs404(): void
    {
        $this->actingAsSuperAdmin();

        $this->putJson('/api/member/999999/fsgt', ['registered' => true, 'licenseNumber' => 'A1']);

        $this->assertJsonResponse(404);
    }

    /** Sans licence pour la saison, il n'y a rien à déclarer. */
    public function testMemberWithoutALicenseForTheSeasonIs404(): void
    {
        $this->actingAsSuperAdmin();
        $member = $this->licensedMember();

        $this->putJson('/api/member/'.$member->getId().'/fsgt', [
            'registered' => true,
            'licenseNumber' => 'A1',
            'season' => '2019-2020',
        ]);

        $body = $this->assertJsonResponse(404);
        $this->assertStringContainsString('2019-2020', $body['message']);
    }

    // ── Portée saison ───────────────────────────────────────────────────────

    /**
     * Le cœur du sujet : inscrire pour une saison ne doit rien changer à l'autre.
     * L'an prochain, tout est à refaire.
     */
    public function testRegistrationIsScopedToItsSeason(): void
    {
        $this->actingAsSuperAdmin();
        $member = $this->licensedMember();
        $this->alsoLicensedFor($member, '2020-2021');

        $this->putJson('/api/member/'.$member->getId().'/fsgt', [
            'registered' => true,
            'licenseNumber' => 'ANCIEN-1',
            'season' => '2020-2021',
        ]);
        $this->assertJsonResponse(200);

        $this->assertTrue($this->licenseOf($member, '2020-2021')->isFsgtRegistered());
        $this->assertFalse(
            $this->licenseOf($member, $this->currentSeason())->isFsgtRegistered(),
            "L'inscription d'une saison passée ne vaut pas pour la saison courante",
        );
    }

    // ── Liste paginée ───────────────────────────────────────────────────────

    public function testPaginatedListExposesTheFsgtFlagAndTheLicenseNumber(): void
    {
        $this->actingAsSuperAdmin();
        $member = $this->licensedMember();

        $this->getJson('/api/member/paginated');
        $row = $this->assertJsonResponse(200)['data'][0];
        $this->assertFalse($row['fsgtRegistered']);
        $this->assertNull($row['licenseNumber']);

        $this->putJson('/api/member/'.$member->getId().'/fsgt', ['registered' => true, 'licenseNumber' => 'FSGT-7']);
        $this->assertJsonResponse(200);

        $this->getJson('/api/member/paginated');
        $row = $this->assertJsonResponse(200)['data'][0];
        $this->assertTrue($row['fsgtRegistered']);
        $this->assertSame('FSGT-7', $row['licenseNumber']);
    }

    /**
     * Un renouvellement arrive avec son ancien numéro : il ne doit surtout pas
     * apparaître comme déjà inscrit pour la saison.
     */
    public function testAnExistingLicenseNumberDoesNotCountAsRegistered(): void
    {
        $this->actingAsSuperAdmin();
        $member = $this->licensedMember();
        $member->setLicenseNumber('ANCIEN-NUMERO');
        $this->em()->flush();

        $this->getJson('/api/member/paginated');

        $row = $this->assertJsonResponse(200)['data'][0];
        $this->assertSame('ANCIEN-NUMERO', $row['licenseNumber']);
        $this->assertFalse($row['fsgtRegistered']);
    }

    /**
     * Le bug d'origine : un numéro sur la fiche, un autre dans la colonne FSGT.
     * Il n'y a plus qu'un seul numéro, celui du membre — quelle que soit la
     * porte par laquelle on l'a saisi.
     */
    public function testTheNumberEditedOnTheMemberIsTheOneListedInTheFsgtColumn(): void
    {
        $this->actingAsSuperAdmin();
        $member = $this->licensedMember();
        $this->putJson('/api/member/'.$member->getId().'/fsgt', ['registered' => true, 'licenseNumber' => 'AVANT']);
        $this->assertJsonResponse(200);

        $member->setLicenseNumber('APRES');
        $this->em()->flush();

        $this->getJson('/api/member/paginated');
        $row = $this->assertJsonResponse(200)['data'][0];
        $this->assertSame('APRES', $row['licenseNumber']);
        $this->assertTrue($row['fsgtRegistered'], "Corriger le numéro ne retire pas l'inscription");
    }

    public function testPaginatedListCanBeFilteredOnTheFsgtFlag(): void
    {
        $this->actingAsSuperAdmin();
        $registered = $this->licensedMember('Inscrit', 'Alpha');
        $this->licensedMember('Absent', 'Beta');
        $this->putJson('/api/member/'.$registered->getId().'/fsgt', ['registered' => true, 'licenseNumber' => 'A1']);
        $this->assertJsonResponse(200);

        $this->getJson('/api/member/paginated?fsgtRegistered=true');
        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertSame('Inscrit', $body['data'][0]['firstName']);

        $this->getJson('/api/member/paginated?fsgtRegistered=false');
        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertSame('Absent', $body['data'][0]['firstName']);

        $this->getJson('/api/member/paginated');
        $this->assertSame(2, $this->assertJsonResponse(200)['total'], 'Sans filtre, les deux');
    }

    // ── Outils ──────────────────────────────────────────────────────────────

    private function licensedMember(string $firstName = 'Jean', string $lastName = 'Dupont'): Member
    {
        return $this->aMember()->named($firstName, $lastName)
            ->licensedFor($this->currentSeason())->persist();
    }

    /**
     * `LicenseBuilder` repasse le membre en PENDING_VALIDATION (il modélise une
     * demande entrante) : on le remet ACTIVE, sinon il sort des listes.
     */
    private function alsoLicensedFor(Member $member, string $season): void
    {
        $this->aLicense()->forMember($member)->inSeason($season)
            ->withStatus(LicenseStatus::VALIDEE)->persist();

        $member->setStatus(MemberStatus::ACTIVE);
        $this->em()->flush();
    }

    private function licenseOf(Member $member, string $season): License
    {
        $license = static::getContainer()->get(LicenseRepository::class)
            ->findOneByMemberAndSeason($member, $season);
        $this->assertNotNull($license);

        return $license;
    }

    private function currentSeason(): string
    {
        return static::getContainer()->get(SeasonProvider::class)->current();
    }
}
