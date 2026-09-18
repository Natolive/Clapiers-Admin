<?php

namespace App\Tests\Functional;

use App\Common\Service\MemberMediaSeeder;
use App\Common\Service\SeasonProvider;
use App\Entity\AppUser;
use App\Entity\Enum\LicenseStatus;
use App\Entity\Enum\MemberStatus;
use App\Entity\Enum\PaymentState;
use App\Entity\License;
use App\Entity\Member;
use App\Entity\MemberDocument;
use App\Entity\Payment;
use App\Entity\Team;
use App\Repository\MemberDocumentRepository;
use App\Tests\Support\Fake\FakeBunnyStorageClient;
use App\Tests\Support\ApiTestCase;

/**
 * /api/member — la classe entière est protégée par ROLE_SUPER_ADMIN.
 */
class MemberApiTest extends ApiTestCase
{
    public function testListAllMembers(): void
    {
        $team = $this->aTeam()->persist();
        $this->aMember()->inTeams($team)->persist();
        $this->aMember()->inTeams($team)->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member');

        $body = $this->assertJsonResponse(200);
        $this->assertCount(2, $body);
    }

    /**
     * GET /api/member alimente le sélecteur « Associer un licencié ». Il ne doit
     * proposer que des ACTIVE : une demande en attente ou refusée n'est pas un
     * licencié à qui rattacher un compte.
     */
    public function testListAllMembersReturnsOnlyActiveOnes(): void
    {
        $team = $this->aTeam()->persist();
        $this->aMember()->inTeams($team)->named('Alice', 'Active')->persist();
        $pending = $this->aMember()->inTeams($team)->named('Bob', 'Attente')->persist();
        $rejected = $this->aMember()->inTeams($team)->named('Carl', 'Refuse')->persist();

        $pending->setStatus(MemberStatus::PENDING_VALIDATION);
        $rejected->setStatus(MemberStatus::REJECTED);
        $this->em()->flush();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member');

        $body = $this->assertJsonResponse(200);
        $this->assertSame(['Alice'], array_column($body, 'firstName'));
    }

    /**
     * MemberController est SUPER_ADMIN de bout en bout. Un `#[IsGranted]` de
     * méthode s'y **ajoute** au lieu de remplacer celui de la classe : la
     * photo de profil, qui doit servir les coachs, vit donc dans son propre
     * contrôleur (cf. les tests de photo plus bas).
     */
    public function testAdminIsForbiddenOnEveryMemberRoute(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/member');
        $this->assertJsonResponse(403);

        $this->deleteJson('/api/member/1');
        $this->assertJsonResponse(403);
    }

    public function testUnauthenticatedIsRejected(): void
    {
        $this->getJson('/api/member');

        $this->assertJsonResponse(401);

        $this->deleteJson('/api/member/1');
        $this->assertJsonResponse(401);
    }

    // ── DELETE /api/member/{id} ─────────────────────────────────────────────

    /**
     * Suppression douce : la fiche est datée, rien n'est détruit. Licences,
     * paiements, médiathèque et fichiers stockés doivent survivre intacts — la
     * valeur d'un soft delete tient entièrement à cette garantie.
     */
    public function testDeleteKeepsLicensesPaymentsAndMedia(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->persist();
        $license = $this->aLicense()->forMember($member)->persist();
        $account = $this->aUser()->admin()->linkedTo($member)->persist();

        $payment = (new Payment())
            ->setLicense($license)
            ->setAmount(12000)
            ->setState(PaymentState::AUTHORIZED);
        $this->em()->persist($payment);

        static::getContainer()->get(MemberMediaSeeder::class)->ensureRootFolders($member);
        $this->em()->flush();
        $slot = static::getContainer()->get(MemberDocumentRepository::class)
            ->findRootDocumentSlot($member, 'identity_photo');
        $this->assertNotNull($slot);
        $bunny = static::getContainer()->get(FakeBunnyStorageClient::class);
        $bunny->seed('pp.png', 'PNGDATA');
        $slot->setFile('pp.png', 'photo.png', 'image/png', 7);
        $this->em()->flush();

        [$memberId, $licenseId, $paymentId, $accountId, $teamId] =
            [$member->getId(), $license->getId(), $payment->getId(), $account->getId(), $team->getId()];
        $mediaCount = count($this->em()->getRepository(MemberDocument::class)->findAll());

        $this->actingAsSuperAdmin();
        $this->deleteJson('/api/member/'.$memberId);

        $body = $this->assertJsonResponse(200);
        $this->assertSame($memberId, $body['id']);
        $this->assertTrue($body['deleted']);

        $this->em()->clear();

        // Rien n'est détruit. La licence est masquée avec son membre (cascade),
        // le paiement et la médiathèque restent visibles — ils ne sont
        // atteignables qu'à travers leur parent, déjà masqué.
        $this->assertNull($this->em()->getRepository(License::class)->find($licenseId));
        $this->assertNotNull($this->em()->getRepository(Payment::class)->find($paymentId));
        $this->assertCount($mediaCount, $this->em()->getRepository(MemberDocument::class)->findAll());
        $this->assertTrue($bunny->has('pp.png'), 'Le fichier stocké doit survivre');
        $this->assertNotNull($this->em()->getRepository(Team::class)->find($teamId));

        // Le compte lié est masqué lui aussi, mais garde son lien vers le
        // membre : le couple reste restaurable.
        $this->assertNull($this->em()->getRepository(AppUser::class)->find($accountId));

        // Mais le membre est invisible : le filtre Doctrine le masque partout.
        // clear() obligatoire — getMember() vient de charger un proxy, et find()
        // le rendrait depuis l'identity map sans jamais interroger la base.
        $this->em()->clear();
        $this->assertNull($this->em()->getRepository(Member::class)->find($memberId));

        $this->getJson('/api/member');
        $this->assertCount(0, $this->assertJsonResponse(200));

        // Il n'est masqué que par le filtre : la ligne est bien en base, datée.
        $this->em()->getFilters()->disable('softdeleteable');
        $stillThere = $this->em()->getRepository(Member::class)->find($memberId);
        $this->assertNotNull($stillThere);
        $this->assertTrue($stillThere->isDeleted());

        $licence = $this->em()->getRepository(License::class)->find($licenseId);
        $this->assertNotNull($licence, 'La licence est masquée, pas supprimée');
        $this->assertTrue($licence->isDeleted());
    }

    /**
     * Une licence est atteignable par son propre `accessToken`, sans passer par
     * le membre. Sans suppression douce en cascade elle survivrait en pointant
     * vers une ligne masquée, et `getMember()` — non nullable — ferait répondre
     * 500 au parcours public de paiement au lieu d'un 404 lisible.
     */
    public function testDeleteAlsoHidesTheLicenceFromItsPublicMagicLink(): void
    {
        $member = $this->aMember()->persist();
        $this->aLicense()->forMember($member)->withToken('tok-supprime')
            ->withStatus(LicenseStatus::VALIDEE)->persist();

        $this->actingAsSuperAdmin();
        $this->deleteJson('/api/member/'.$member->getId());
        $this->assertJsonResponse(200);

        $this->getJson('/api/public/license/tok-supprime');
        $body = $this->assertJsonResponse(404);
        $this->assertSame('Licence introuvable', $body['message']);
    }

    /**
     * Un licencié supprimé ne doit plus retrouver son compte. Le provider de
     * sécurité étant un provider `entity`, il passe par l'ORM et le filtre le
     * masque : ni un JWT déjà émis, ni une reconnexion par mot de passe ne
     * doivent passer.
     */
    public function testTheLinkedAccountCanNoLongerAuthenticate(): void
    {
        $member = $this->aMember()->persist();
        $account = $this->aUser()->superAdmin()->withEmail('parti@test.fr')
            ->withPassword('secret123')->linkedTo($member)->persist();

        $this->actingAsSuperAdmin();
        $this->deleteJson('/api/member/'.$member->getId());
        $this->assertJsonResponse(200);

        // Le jeton émis avant la suppression ne vaut plus rien : l'utilisateur
        // est rechargé depuis le provider à chaque requête.
        $this->actingAs($account);
        $this->getJson('/api/user/me');
        $this->assertSame(401, $this->response()->getStatusCode());

        $this->postJson('/api/login', ['email' => 'parti@test.fr', 'password' => 'secret123']);
        $this->assertSame(401, $this->response()->getStatusCode());

        // Et il sort de l'administration des utilisateurs.
        $this->actingAsSuperAdmin();
        $this->getJson('/api/user/paginated?page=1&limit=50');
        $emails = array_column($this->assertJsonResponse(200)['data'], 'email');
        $this->assertNotContains('parti@test.fr', $emails);
    }

    /**
     * Les tables de liaison ne sont couvertes ni par le filtre (ce ne sont pas
     * des entités) ni par l'horodatage (qui n'efface rien) : elles doivent être
     * vidées explicitement, sinon elles affirment une appartenance à une équipe
     * qui n'existe plus et tout comptage en SQL brut surcompterait.
     */
    public function testDeleteClearsTeamJoinRows(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->persist();
        $this->aUser()->admin()->managing($team)->linkedTo($member)->persist();

        $conn = $this->em()->getConnection();
        $this->assertSame(1, (int) $conn->fetchOne('SELECT COUNT(*) FROM member_team'));
        $this->assertSame(1, (int) $conn->fetchOne('SELECT COUNT(*) FROM app_user_team'));

        $this->actingAsSuperAdmin();
        $this->deleteJson('/api/member/'.$member->getId());
        $this->assertJsonResponse(200);

        $this->assertSame(0, (int) $conn->fetchOne('SELECT COUNT(*) FROM member_team'));
        $this->assertSame(0, (int) $conn->fetchOne('SELECT COUNT(*) FROM app_user_team'));

        // L'équipe elle-même n'est pas touchée, et n'affiche plus le coach.
        $this->getJson('/api/team');
        $teams = $this->assertJsonResponse(200);
        $this->assertCount(1, $teams);
        $this->assertSame([], $teams[0]['coaches']);
    }

    /** Le filtre masque aussi le membre à la suppression : rejouer donne 404. */
    public function testDeletingTwiceReturns404(): void
    {
        $member = $this->aMember()->persist();

        $this->actingAsSuperAdmin();
        $this->deleteJson('/api/member/'.$member->getId());
        $this->assertJsonResponse(200);

        $this->deleteJson('/api/member/'.$member->getId());
        $this->assertJsonResponse(404);
    }

    public function testDeleteUnknownMemberReturns404(): void
    {
        $this->actingAsSuperAdmin();
        $this->deleteJson('/api/member/999999');

        $this->assertJsonResponse(404);
    }

    // ── POST/PUT /api/member ────────────────────────────────────────────────

    public function testCreateMemberWithMultipleTeams(): void
    {
        $teamA = $this->aTeam()->persist();
        $teamB = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/member', $this->memberPayload([
            'teamIds' => [$teamA->getId(), $teamB->getId()],
        ]));

        $body = $this->assertJsonResponse(200);
        $this->assertSame('Lucie', $body['firstName']);
        $this->assertCount(2, $body['teams']);
        $this->assertNotEmpty($body['color'], 'A random color should be generated');

        $saved = $this->em()->getRepository(Member::class)->find($body['id']);
        $this->assertNotNull($saved);
        $this->assertCount(2, $saved->getTeams());
    }

    public function testCreateMemberWithoutTeamIsRejected(): void
    {
        $this->actingAsSuperAdmin();
        $this->postJson('/api/member', $this->memberPayload(['teamIds' => []]));

        $this->assertJsonResponse(422);
    }

    public function testCreateMemberWithInvalidEmailIsRejected(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/member', $this->memberPayload([
            'teamIds' => [$team->getId()],
            'email' => 'not-an-email',
        ]));

        $this->assertJsonResponse(422);
    }

    public function testCreateMemberWithInvalidPhoneNumberIsRejected(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/member', $this->memberPayload([
            'teamIds' => [$team->getId()],
            'phoneNumber' => '12',
        ]));

        $this->assertJsonResponse(422);
    }

    public function testCreateMemberWithUnknownTeamReturns404(): void
    {
        $this->actingAsSuperAdmin();
        $this->postJson('/api/member', $this->memberPayload(['teamIds' => [999999]]));

        $body = $this->assertJsonResponse(404);
        $this->assertSame('Team not found', $body['message']);
    }

    public function testUpdateMemberReplacesTeamsAndFields(): void
    {
        $oldTeam = $this->aTeam()->persist();
        $newTeam = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($oldTeam)->persist();

        $this->actingAsSuperAdmin();
        $this->putJson('/api/member', $this->memberPayload([
            'id' => $member->getId(),
            'firstName' => 'Renommée',
            'teamIds' => [$newTeam->getId()],
        ]));

        $body = $this->assertJsonResponse(200);
        $this->assertSame($member->getId(), $body['id']);
        $this->assertSame('Renommée', $body['firstName']);
        $this->assertCount(1, $body['teams']);
        $this->assertSame($newTeam->getId(), $body['teams'][0]['id']);
    }

    public function testUpdateUnknownMemberFails(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->putJson('/api/member', $this->memberPayload([
            'id' => 999999,
            'teamIds' => [$team->getId()],
        ]));

        $body = $this->assertJsonResponse(400);
        $this->assertSame('Member not found', $body['message']);
    }

    // ── GET /api/member/paginated ───────────────────────────────────────────

    public function testPaginatedMembersReturnsDataAndTotal(): void
    {
        $season = $this->currentSeason();
        $team = $this->aTeam()->persist();
        for ($i = 0; $i < 3; ++$i) {
            $this->aMember()->inTeams($team)->licensedFor($season)->persist();
        }

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?page=1&limit=2');

        $body = $this->assertJsonResponse(200);
        $this->assertSame(3, $body['total']);
        $this->assertCount(2, $body['data']);
    }

    public function testPaginatedMembersExcludesNonActiveMembers(): void
    {
        $active = $this->aMember()->named('Active', 'Membre')->licensedFor($this->currentSeason())->persist();

        $pending = $this->aMember()->named('Pending', 'Membre')->persist();
        $pending->setStatus(MemberStatus::PENDING_VALIDATION);
        $rejected = $this->aMember()->named('Rejected', 'Membre')->persist();
        $rejected->setStatus(MemberStatus::REJECTED);
        $this->em()->flush();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?page=1&limit=50');

        // Seuls les membres actifs apparaissent (pas les demandes en attente/refusées).
        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertSame($active->getId(), $body['data'][0]['id']);
    }

    public function testPaginatedMembersFiltersBySearch(): void
    {
        $season = $this->currentSeason();
        $team = $this->aTeam()->persist();
        $this->aMember()->named('Zoé', 'Unique')->inTeams($team)->licensedFor($season)->persist();
        $this->aMember()->named('Marc', 'Commun')->inTeams($team)->licensedFor($season)->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?search=Zoé');

        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertSame('Zoé', $body['data'][0]['firstName']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('searchQueries')]
    public function testPaginatedMembersSearchIsForgiving(string $query, bool $shouldMatch): void
    {
        $season = $this->currentSeason();
        $team = $this->aTeam()->persist();
        $this->aMember()->named('Jean-Rémi', 'Dupont')
            ->withEmail('jean.dupont@test.fr')
            ->withPhoneNumber('0612345001')
            ->inTeams($team)->licensedFor($season)->persist();
        $this->aMember()->named('Marc', 'Commun')->withPhoneNumber('0799999999')
            ->inTeams($team)->licensedFor($season)->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?limit=50&search='.urlencode($query));

        $body = $this->assertJsonResponse(200);
        if ($shouldMatch) {
            $this->assertSame(1, $body['total'], sprintf('« %s » doit trouver Jean Dupont', $query));
            $this->assertSame('Dupont', $body['data'][0]['lastName']);
        } else {
            $this->assertSame(0, $body['total'], sprintf('« %s » ne doit rien trouver', $query));
        }
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function searchQueries(): iterable
    {
        yield 'un seul mot' => ['dupont', true];
        yield 'casse indifférente' => ['DUPONT', true];
        yield 'prénom puis nom' => ['jean dup', true];
        yield 'nom puis prénom' => ['dupont jean', true];
        yield 'espaces superflus' => ['  jean   dupont  ', true];
        yield 'email partiel' => ['jean.dupont@', true];
        yield 'téléphone brut' => ['0612345', true];
        yield 'téléphone formaté' => ['06 12 34 50', true];
        yield 'téléphone en points' => ['06.12.34', true];
        yield 'saisie sans accent' => ['jean-remi', true];
        yield 'saisie avec accent' => ['jean-rémi dupont', true];
        yield 'accent et casse mélangés' => ['RÉMI', true];
        yield 'mot inconnu en plus' => ['jean zidane', false];
        yield 'aucun rapport' => ['zidane', false];
    }

    public function testPaginatedMembersSearchFindsInternationalPhoneTypedNationally(): void
    {
        $season = $this->currentSeason();
        $team = $this->aTeam()->persist();
        $this->aMember()->withPhoneNumber('+33 6 12 34 50 04')->inTeams($team)->licensedFor($season)->persist();
        $this->aMember()->withPhoneNumber('0799999999')->inTeams($team)->licensedFor($season)->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?limit=50&search='.urlencode('06 12 34 50'));

        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
    }

    public function testPaginatedMembersBlankSearchIsIgnored(): void
    {
        $season = $this->currentSeason();
        $team = $this->aTeam()->persist();
        $this->aMember()->inTeams($team)->licensedFor($season)->persist();
        $this->aMember()->inTeams($team)->licensedFor($season)->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?limit=50&search='.urlencode('   '));

        $body = $this->assertJsonResponse(200);
        $this->assertSame(2, $body['total']);
    }

    public function testPaginatedMembersSearchCombinesWithOtherFilters(): void
    {
        $season = $this->currentSeason();
        $teamA = $this->aTeam()->persist();
        $teamB = $this->aTeam()->persist();
        $this->aMember()->named('Jean', 'Dupont')->inTeams($teamA)->licensedFor($season)->persist();
        $this->aMember()->named('Jean', 'Dupont')->inTeams($teamB)->licensedFor($season)->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?search=jean+dupont&teamId='.$teamA->getId());

        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
    }

    public function testPaginatedMembersFiltersByTeam(): void
    {
        $season = $this->currentSeason();
        $teamA = $this->aTeam()->persist();
        $teamB = $this->aTeam()->persist();
        $inA = $this->aMember()->inTeams($teamA)->licensedFor($season)->persist();
        $this->aMember()->inTeams($teamB)->licensedFor($season)->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?teamId='.$teamA->getId());

        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertSame($inA->getId(), $body['data'][0]['id']);
    }

    public function testPaginatedMembersFiltersByLicensePaid(): void
    {
        $season = $this->currentSeason();
        $team = $this->aTeam()->persist();
        $paid = $this->aMember()->inTeams($team)->persist();
        $this->aLicense()->forMember($paid)->inSeason($season)->withStatus(LicenseStatus::PAYEE)->persist();
        $paid->setStatus(MemberStatus::ACTIVE); // le builder de licence l'avait passé en attente
        $this->aMember()->inTeams($team)->licensedFor($season)->persist(); // actif, licence validée mais non payée
        $this->em()->flush();

        $paidId = $paid->getId();
        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?licensePaid=true');
        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertSame($paidId, $body['data'][0]['id']);

        // Le filtre inverse ne renvoie que le membre actif sans licence payée.
        $this->getJson('/api/member/paginated?licensePaid=false');
        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertNotSame($paidId, $body['data'][0]['id']);
    }

    public function testLicensePaidFlagIsScopedToCurrentSeason(): void
    {
        $season = $this->currentSeason();
        $team = $this->aTeam()->persist();

        $current = $this->aMember()->named('Àjour', 'Saison')->inTeams($team)->persist();
        $this->aLicense()->forMember($current)->inSeason($season)->withStatus(LicenseStatus::PAYEE)->persist();
        $current->setStatus(MemberStatus::ACTIVE);

        // Licencié cette saison (validée, non payée) MAIS payé une saison passée :
        // il apparaît, et le flag « payée » ne doit voir que la saison courante.
        $past = $this->aMember()->named('Payé', 'Avant')->inTeams($team)->licensedFor($season)->persist();
        $this->aLicense()->forMember($past)->inSeason('2000-2001')->withStatus(LicenseStatus::PAYEE)->persist();
        $past->setStatus(MemberStatus::ACTIVE);
        $this->em()->flush();

        $currentId = $current->getId();
        $pastId = $past->getId();

        // Collections rechargées depuis la base : le flag reflète bien la BDD.
        $this->em()->clear();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?limit=50');
        $body = $this->assertJsonResponse(200);

        $paidById = [];
        foreach ($body['data'] as $row) {
            $paidById[$row['id']] = $row['licensePaid'];
        }
        $this->assertTrue($paidById[$currentId], 'Licence payée saison courante → à jour');
        $this->assertFalse($paidById[$pastId], 'Licence payée d’une saison passée → pas à jour');
    }

    public function testPaginatedMembersScopeToRequestedSeason(): void
    {
        $this->aMember()->named('SaisonA', 'Licencie')->licensedFor('2030-2031')->persist();
        $this->aMember()->named('SaisonB', 'Licencie')->licensedFor('2031-2032')->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?season=2030-2031&limit=50');

        $body = $this->assertJsonResponse(200);
        $this->assertSame(1, $body['total']);
        $this->assertSame('SaisonA', $body['data'][0]['firstName']);
    }

    public function testPaginatedMembersSortsByLastNameDesc(): void
    {
        $season = $this->currentSeason();
        $team = $this->aTeam()->persist();
        $this->aMember()->named('A', 'Aaa')->inTeams($team)->licensedFor($season)->persist();
        $this->aMember()->named('B', 'Zzz')->inTeams($team)->licensedFor($season)->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?sortField=lastName&sortOrder=desc');

        $body = $this->assertJsonResponse(200);
        $this->assertSame('Zzz', $body['data'][0]['lastName']);
    }

    public function testPaginatedExposesLicenseDocumentFlagFromMedia(): void
    {
        $season = static::getContainer()->get(SeasonProvider::class)->current();
        $withLicense = $this->aMember()->named('Avec', 'Licence')->licensedFor($season)->persist();
        $without = $this->aMember()->named('Sans', 'Licence')->licensedFor($season)->persist();

        // Remplit le slot Licence de la saison courante d'un seul membre.
        static::getContainer()->get(MemberMediaSeeder::class)->ensureSeason($withLicense, $season);
        $this->em()->flush();
        $slot = static::getContainer()->get(MemberDocumentRepository::class)
            ->findDefaultSlot($withLicense, $season, 'license');
        $this->assertNotNull($slot);
        $slot->setFile('x.pdf', 'x.pdf', 'application/pdf', 10);
        $this->em()->flush();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/paginated?limit=50');

        $body = $this->assertJsonResponse(200);
        $flags = [];
        foreach ($body['data'] as $row) {
            $flags[$row['id']] = $row['hasLicenseDocument'];
        }
        $this->assertTrue($flags[$withLicense->getId()], 'Slot Licence rempli → drapeau vrai');
        $this->assertFalse($flags[$without->getId()], 'Aucun document → drapeau faux');
    }

    // ── GET /api/member/team/{teamId} ───────────────────────────────────────

    public function testMembersByTeamReturnsOnlyThatTeam(): void
    {
        $season = $this->currentSeason();
        $teamA = $this->aTeam()->persist();
        $teamB = $this->aTeam()->persist();
        $inA = $this->aMember()->inTeams($teamA)->licensedFor($season)->persist();
        $inBoth = $this->aMember()->inTeams($teamA, $teamB)->licensedFor($season)->persist();
        $this->aMember()->inTeams($teamB)->licensedFor($season)->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/team/'.$teamA->getId());

        $body = $this->assertJsonResponse(200);
        $ids = array_column($body, 'id');
        sort($ids);
        $expected = [$inA->getId(), $inBoth->getId()];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function testMembersByTeamScopeToRequestedSeason(): void
    {
        $team = $this->aTeam()->persist();
        $this->aMember()->named('SaisonA', 'Equipe')->inTeams($team)->licensedFor('2030-2031')->persist();
        $this->aMember()->named('SaisonB', 'Equipe')->inTeams($team)->licensedFor('2031-2032')->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/team/'.$team->getId().'?season=2030-2031');

        $body = $this->assertJsonResponse(200);
        $this->assertCount(1, $body);
        $this->assertSame('SaisonA', $body[0]['firstName']);
    }

    public function testMembersByTeamRejectsMalformedSeason(): void
    {
        // Le format de saison est validé par la contrainte générique #[Season]
        // via MapQueryString ; une valeur invalide est rejetée (404 = statut par
        // défaut de MapQueryString en cas d'échec de validation).
        $team = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/team/'.$team->getId().'?season=not-a-season');

        $this->assertJsonResponse(404);
    }

    public function testMembersByUnknownTeamReturns404(): void
    {
        $this->actingAsSuperAdmin();
        $this->getJson('/api/member/team/999999');

        $body = $this->assertJsonResponse(404);
        $this->assertSame('Team not found', $body['message']);
    }

    // ── Photo d'identité : lecture depuis la médiathèque ─────────────────────
    //
    // La route sert deux publics : le SUPER_ADMIN voit tout le club, un coach
    // (ROLE_ADMIN) seulement les licenciés d'une de ses équipes. La photo est
    // une donnée personnelle, pas un trombinoscope ouvert.

    public function testMemberListCarriesASignedCdnUrlForTheProfilePicture(): void
    {
        $member = $this->aMember()->persist();
        $this->givePhotoInMediatheque($member);

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member');

        $row = $this->rowFor($this->assertJsonResponse(200), $member->getId());
        $url = $row['profilePictureUrl'];
        $this->assertNotNull($url);
        $this->assertSame(self::TEST_BUNNY_CDN_URL.'/member-media/pp.png', strtok($url, '?'));

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertNotEmpty($query['token']);
        $this->assertGreaterThan(time(), (int) $query['expires']);
    }

    public function testProfilePictureUrlIsNullWhenTheMemberHasNoPhoto(): void
    {
        $member = $this->aMember()->persist();

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member');

        $this->assertNull($this->rowFor($this->assertJsonResponse(200), $member->getId())['profilePictureUrl']);
    }

    /** Sans pull zone configurée, plus rien ne se lit : pas d'URL, pas de lien mort. */
    public function testProfilePictureUrlIsNullWithoutAPullZone(): void
    {
        $member = $this->aMember()->persist();
        $this->givePhotoInMediatheque($member);
        $this->configureBunny(self::TEST_BUNNY_URL, cdnUrl: '');

        $this->actingAsSuperAdmin();
        $this->getJson('/api/member');

        $this->assertNull($this->rowFor($this->assertJsonResponse(200), $member->getId())['profilePictureUrl']);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, mixed>
     */
    private function rowFor(array $rows, ?int $memberId): array
    {
        foreach ($rows as $row) {
            if ($row['id'] === $memberId) {
                return $row;
            }
        }

        $this->fail('Licencié absent de la liste');
    }

    /** Remplit le slot photo de profil dans la médiathèque du membre. */
    private function givePhotoInMediatheque(Member $member): void
    {
        static::getContainer()->get(MemberMediaSeeder::class)->ensureRootFolders($member);
        $this->em()->flush();
        $slot = static::getContainer()->get(MemberDocumentRepository::class)
            ->findRootDocumentSlot($member, 'identity_photo');
        $this->assertNotNull($slot);

        static::getContainer()->get(FakeBunnyStorageClient::class)->seed('pp.png', 'PNGDATA');
        $slot->setFile('pp.png', 'photo.png', 'image/png', 7);
        $this->em()->flush();
    }

    // ── Création avec licence ───────────────────────────────────────────────

    /**
     * Le vrai besoin : une fiche créée à la main sans licence n'apparaît dans
     * aucune liste, toutes scopées à la saison. Le bloc `license` la rend
     * visible tout de suite.
     */
    public function testCreateMemberWithALicenseMakesItVisibleInTheSeason(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/member', $this->memberPayload([
            'teamIds' => [$team->getId()],
            'license' => ['amount' => 9000, 'helloAssoTierId' => 7],
        ]));

        $body = $this->assertJsonResponse(200);

        $license = $this->em()->getRepository(License::class)
            ->findOneBy(['member' => $body['id'], 'season' => $this->currentSeason()]);
        $this->assertNotNull($license);
        $this->assertSame(LicenseStatus::VALIDEE, $license->getStatus());
        $this->assertSame(9000, $license->getAmount());
        $this->assertNotNull($license->getApprovedAt());
        $this->assertNotNull($license->getAccessToken(), 'Le lien de paiement doit rester renvoyable');
        $this->assertEmailCount(0, 'Pas de mail sans demande explicite');

        $this->getJson('/api/member/paginated?season='.$this->currentSeason());
        $this->assertSame(['Lucie'], array_column($this->assertJsonResponse(200)['data'], 'firstName'));
    }

    public function testCreateMemberWithoutTheLicenseBlockCreatesNoLicense(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/member', $this->memberPayload(['teamIds' => [$team->getId()]]));

        $body = $this->assertJsonResponse(200);
        $this->assertNull(
            $this->em()->getRepository(License::class)->findOneBy(['member' => $body['id']]),
        );
    }

    public function testCreateMemberCanSendThePaymentLinkEmail(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/member', $this->memberPayload([
            'teamIds' => [$team->getId()],
            'license' => ['amount' => 9000, 'helloAssoTierId' => 7, 'sendPaymentEmail' => true],
        ]));

        $body = $this->assertJsonResponse(200);
        $license = $this->em()->getRepository(License::class)->findOneBy(['member' => $body['id']]);

        $this->assertEmailCount(1);
        $this->assertEmailHtmlBodyContains($this->getMailerMessage(), '/licence/'.$license->getAccessToken());
    }

    /** Le mail annonce un montant : sans tarif il annoncerait 0 €. */
    public function testSendingThePaymentEmailWithoutAnAmountIsRejected(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/member', $this->memberPayload([
            'teamIds' => [$team->getId()],
            'license' => ['sendPaymentEmail' => true],
        ]));

        $body = $this->assertJsonResponse(400);
        $this->assertStringContainsString('tarif', $body['message']);
        $this->assertEmailCount(0);
    }

    public function testCreateMemberWithAnInvalidLicenseSeasonIsRejected(): void
    {
        $team = $this->aTeam()->persist();

        $this->actingAsSuperAdmin();
        $this->postJson('/api/member', $this->memberPayload([
            'teamIds' => [$team->getId()],
            'license' => ['season' => '2026'],
        ]));

        $this->assertJsonResponse(422);
    }

    /** Rattrapage d'une fiche déjà créée sans licence, par la modification. */
    public function testUpdatingAMemberCanAddTheMissingLicense(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->persist();

        $this->actingAsSuperAdmin();
        $this->putJson('/api/member', $this->memberPayload([
            'id' => $member->getId(),
            'teamIds' => [$team->getId()],
            'license' => ['season' => '2020-2021'],
        ]));

        $this->assertJsonResponse(200);
        $this->assertNotNull(
            $this->em()->getRepository(License::class)
                ->findOneBy(['member' => $member->getId(), 'season' => '2020-2021']),
        );
    }

    public function testAddingASecondLicenseForTheSameSeasonIsRejected(): void
    {
        $team = $this->aTeam()->persist();
        $member = $this->aMember()->inTeams($team)->licensedFor($this->currentSeason())->persist();

        $this->actingAsSuperAdmin();
        $this->putJson('/api/member', $this->memberPayload([
            'id' => $member->getId(),
            'teamIds' => [$team->getId()],
            'license' => [],
        ]));

        $body = $this->assertJsonResponse(409);
        $this->assertStringContainsString('déjà une licence', $body['message']);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function currentSeason(): string
    {
        return static::getContainer()->get(SeasonProvider::class)->current();
    }

    /**
     * Valid creation payload; override any field via $overrides.
     */
    private function memberPayload(array $overrides = []): array
    {
        return array_merge([
            'firstName' => 'Lucie',
            'lastName' => 'Bernard',
            'phoneNumber' => '+33612345678',
            'email' => 'lucie.bernard@test.fr',
            'teamIds' => [],
            'addressStreet' => '2 avenue du Gymnase',
            'addressZip' => '34830',
            'addressCity' => 'Clapiers',
            'gender' => 'female',
            'birthDate' => '2001-04-12',
            'nationality' => 'Française',
            'licenseNumber' => '123456789',
        ], $overrides);
    }
}
