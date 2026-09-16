<?php

namespace App\Tests\Functional;

use App\Application\UseCase\Member\ExportMembers\MemberExportColumn;
use App\Common\Service\SeasonProvider;
use App\Entity\Enum\LicenseStatus;
use App\Entity\Enum\MemberGender;
use App\Entity\Enum\MemberStatus;
use App\Entity\License;
use App\Entity\Member;
use App\Repository\LicenseRepository;
use App\Entity\ValueObject\Address;
use App\Entity\ValueObject\LegalRepresentative;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Fake\FakeBunnyStorageClient;
use OpenSpout\Reader\XLSX\Options as ReaderOptions;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * GET /api/member/export — export Excel des licenciés.
 *
 * La route renvoie un fichier, pas du JSON : chaque test relit le xlsx produit
 * et assert sur son contenu, seule preuve qu'un export est correct.
 */
class MemberExportApiTest extends ApiTestCase
{
    // ── Sécurité ────────────────────────────────────────────────────────────

    public function testExportRequiresAuthentication(): void
    {
        $this->getJson('/api/member/export');

        $this->assertSame(401, $this->response()->getStatusCode());
    }

    public function testExportIsForbiddenForAdmin(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/member/export');

        $this->assertSame(403, $this->response()->getStatusCode());
    }

    // ── Réponse HTTP ────────────────────────────────────────────────────────

    public function testExportRespondsWithAnAttachedXlsxNamedAfterTheSeason(): void
    {
        $this->actingAsSuperAdmin();
        $season = $this->currentSeason();
        $this->aMember()->named('Jean', 'Dupont')->licensedFor($season)->persist();

        $this->getJson('/api/member/export');

        $response = $this->response();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type'),
        );
        $this->assertStringContainsString(
            sprintf('filename=licencies-%s.xlsx', $season),
            (string) $response->headers->get('Content-Disposition'),
        );
        $this->assertStringStartsWith('attachment;', (string) $response->headers->get('Content-Disposition'));
    }

    // ── Colonnes ────────────────────────────────────────────────────────────

    public function testExportWithoutColumnsContainsThemAll(): void
    {
        $this->actingAsSuperAdmin();
        $this->aMember()->named('Jean', 'Dupont')->licensedFor($this->currentSeason())->persist();

        $this->getJson('/api/member/export');

        $rows = $this->readExport();
        $this->assertSame(
            array_map(fn (MemberExportColumn $c) => $c->label(), MemberExportColumn::all()),
            $rows[0],
        );
        $this->assertCount(2, $rows, 'Une ligne d\'en-tête + une ligne par licencié');
    }

    public function testExportKeepsOnlyTheRequestedColumns(): void
    {
        $this->actingAsSuperAdmin();
        $this->aMember()->named('Jean', 'Dupont')->withEmail('jean@test.fr')->licensedFor($this->currentSeason())->persist();

        $this->getJson('/api/member/export?columns[]=firstName&columns[]=email');

        $rows = $this->readExport();
        $this->assertSame(['Prénom', 'Email'], $rows[0]);
        $this->assertSame(['Jean', 'jean@test.fr'], $rows[1]);
    }

    /**
     * L'ordre des colonnes est celui de l'enum, pas celui de la requête : deux
     * exports demandés dans un ordre différent doivent avoir la même tête.
     */
    public function testColumnOrderFollowsTheEnumNotTheRequest(): void
    {
        $this->actingAsSuperAdmin();
        $this->aMember()->named('Jean', 'Dupont')->licensedFor($this->currentSeason())->persist();

        $this->getJson('/api/member/export?columns[]=email&columns[]=firstName&columns[]=lastName');

        $this->assertSame(['Nom', 'Prénom', 'Email'], $this->readExport()[0]);
    }

    public function testUnknownColumnIsRejected(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/member/export?columns[]=firstName&columns[]=numeroDeSecu');

        $this->assertSame(422, $this->response()->getStatusCode());
    }

    public function testEmptyColumnSelectionIsRejected(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/member/export?columns=');

        $this->assertSame(422, $this->response()->getStatusCode());
    }

    public function testInvalidSeasonIsRejected(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/member/export?season=2026');

        $this->assertSame(422, $this->response()->getStatusCode());
    }

    // ── Valeurs exportées ───────────────────────────────────────────────────

    public function testIdentityContactAndAddressColumnsAreExported(): void
    {
        $this->actingAsSuperAdmin();
        $season = $this->currentSeason();
        $member = $this->aMember()
            ->named('Zoé', 'Martin')
            ->withEmail('zoe@test.fr')
            ->withPhoneNumber('+33612345678')
            ->withGender(MemberGender::FEMALE)
            ->licensedFor($season)
            ->persist();
        $member->setBirthDate(new \DateTimeImmutable('2000-03-04'));
        $member->setAddress(new Address('12 rue des Lilas', '34000', 'Montpellier'));
        $this->em()->flush();

        $this->getJson(
            '/api/member/export'
            .'?columns[]=lastName&columns[]=firstName&columns[]=gender&columns[]=birthDate&columns[]=age'
            .'&columns[]=nationality&columns[]=status&columns[]=email&columns[]=phoneNumber'
            .'&columns[]=addressStreet&columns[]=addressZip&columns[]=addressCity'
        );

        $expectedAge = (new \DateTimeImmutable('2000-03-04'))->diff(new \DateTimeImmutable('today'))->y;
        $this->assertSame([
            'Martin', 'Zoé', 'Femme', '04/03/2000', (string) $expectedAge,
            'Française', 'Actif', 'zoe@test.fr', '+33612345678',
            '12 rue des Lilas', '34000', 'Montpellier',
        ], $this->readExport()[1]);
    }

    public function testTeamsColumnJoinsEveryTeamOfTheMember(): void
    {
        $this->actingAsSuperAdmin();
        $teamA = $this->aTeam()->named('Loisir 1')->persist();
        $teamB = $this->aTeam()->named('Compet 2')->persist();
        $this->aMember()->named('Jean', 'Dupont')->inTeams($teamA, $teamB)->licensedFor($this->currentSeason())->persist();

        $this->getJson('/api/member/export?columns[]=teams');

        $this->assertSame(['Loisir 1, Compet 2'], $this->readExport()[1]);
    }

    public function testTeamsColumnIsEmptyForAMemberWithoutTeam(): void
    {
        $this->actingAsSuperAdmin();
        $this->aMember()->named('Jean', 'Dupont')->licensedFor($this->currentSeason())->persist();

        $this->getJson('/api/member/export?columns[]=firstName&columns[]=teams');

        $this->assertSame(['Jean', ''], $this->readExport()[1]);
    }

    public function testLicenseColumnsComeFromTheLicenseOfTheExportedSeason(): void
    {
        $this->actingAsSuperAdmin();
        $season = $this->currentSeason();
        $member = $this->aMember()->named('Jean', 'Dupont')->licensedFor($season, LicenseStatus::PAYEE)->persist();

        $license = $this->licenseOf($member, $season);
        $license->setLicenseNumber('L-2026-001');
        $license->setAmount(12550);
        $license->setApprovedAt(new \DateTimeImmutable('2026-09-10'));
        $license->setHealthDeclaration(true);
        $this->em()->flush();

        $this->getJson(
            '/api/member/export'
            .'?columns[]=licenseNumber&columns[]=licenseStatus&columns[]=licensePaid'
            .'&columns[]=licenseAmount&columns[]=licenseApprovedAt&columns[]=healthDeclaration'
        );

        $this->assertSame(
            ['L-2026-001', 'Payée', 'Oui', '125.5', '10/09/2026', 'Oui'],
            $this->readExport()[1],
        );
    }

    /** Le montant sort en euros (stocké en centimes) et reste un nombre sommable. */
    public function testLicenseAmountIsExportedInEurosAsANumber(): void
    {
        $this->actingAsSuperAdmin();
        $season = $this->currentSeason();
        $member = $this->aMember()->licensedFor($season, LicenseStatus::PAYEE)->persist();
        $this->licenseOf($member, $season)->setAmount(9000);
        $this->em()->flush();

        $this->getJson('/api/member/export?columns[]=licenseAmount');

        // Le type importe plus que la représentation : un « 90 » texte casserait
        // une somme Excel, alors que 90 entier ou 90.0 flottant conviennent.
        $cell = $this->readExportCells()[1][0];
        $this->assertContains(get_debug_type($cell), ['int', 'float']);
        $this->assertEquals(90, $cell);
    }

    public function testUnpaidAndUndeclaredLicenseColumnsStayReadable(): void
    {
        $this->actingAsSuperAdmin();
        $this->aMember()->named('Jean', 'Dupont')->licensedFor($this->currentSeason(), LicenseStatus::VALIDEE)->persist();

        $this->getJson(
            '/api/member/export'
            .'?columns[]=licenseStatus&columns[]=licensePaid&columns[]=licenseAmount'
            .'&columns[]=licenseApprovedAt&columns[]=healthDeclaration'
        );

        // Montant non figé => cellule vide, pas un zéro qui fausserait une somme.
        $this->assertSame(['Validée', 'Non', '', '', ''], $this->readExport()[1]);
    }

    /** À défaut de n° sur la licence, celui saisi sur la fiche membre sert de repli. */
    public function testLicenseNumberFallsBackToTheMemberField(): void
    {
        $this->actingAsSuperAdmin();
        $this->aMember()->withLicenseNumber('FICHE-42')->licensedFor($this->currentSeason())->persist();

        $this->getJson('/api/member/export?columns[]=licenseNumber');

        $this->assertSame(['FICHE-42'], $this->readExport()[1]);
    }

    public function testLegalRepresentativeColumnsAreExported(): void
    {
        $this->actingAsSuperAdmin();
        $member = $this->aMember()->named('Léa', 'Petit')->licensedFor($this->currentSeason())->persist();
        $member->setLegalRepresentative(new LegalRepresentative('Marie', 'Petit', 'marie@test.fr', '+33611223344'));
        $this->em()->flush();

        $this->getJson(
            '/api/member/export'
            .'?columns[]=legalRepFirstName&columns[]=legalRepLastName'
            .'&columns[]=legalRepEmail&columns[]=legalRepPhone'
        );

        $this->assertSame(['Marie', 'Petit', 'marie@test.fr', '+33611223344'], $this->readExport()[1]);
    }

    public function testCreatedAtColumnIsExportedAsADate(): void
    {
        $this->actingAsSuperAdmin();
        $member = $this->aMember()->licensedFor($this->currentSeason())->persist();

        $this->getJson('/api/member/export?columns[]=createdAt');

        $this->assertSame(
            [$member->getCreatedAt()->format('d/m/Y')],
            $this->readExport()[1],
        );
    }

    // ── Population exportée ─────────────────────────────────────────────────

    public function testExportIsSortedByLastNameThenFirstName(): void
    {
        $this->actingAsSuperAdmin();
        $season = $this->currentSeason();
        $this->aMember()->named('Zoé', 'Bernard')->licensedFor($season)->persist();
        $this->aMember()->named('Alice', 'Bernard')->licensedFor($season)->persist();
        $this->aMember()->named('Marc', 'Albert')->licensedFor($season)->persist();

        $this->getJson('/api/member/export?columns[]=lastName&columns[]=firstName');

        $rows = $this->readExport();
        $this->assertSame([
            ['Albert', 'Marc'],
            ['Bernard', 'Alice'],
            ['Bernard', 'Zoé'],
        ], array_slice($rows, 1));
    }

    public function testExportIsScopedToTheCurrentSeasonByDefault(): void
    {
        $this->actingAsSuperAdmin();
        $this->aMember()->named('Actuel', 'Licencie')->licensedFor($this->currentSeason())->persist();
        $this->aMember()->named('Ancien', 'Licencie')->licensedFor('2020-2021')->persist();

        $this->getJson('/api/member/export?columns[]=firstName');

        $this->assertSame([['Actuel']], array_slice($this->readExport(), 1));
    }

    public function testExportFollowsTheRequestedSeason(): void
    {
        $this->actingAsSuperAdmin();
        $this->aMember()->named('Actuel', 'Licencie')->licensedFor($this->currentSeason())->persist();
        $this->aMember()->named('Ancien', 'Licencie')->licensedFor('2020-2021')->persist();

        $this->getJson('/api/member/export?season=2020-2021&columns[]=firstName');

        $this->assertSame([['Ancien']], array_slice($this->readExport(), 1));
        $this->assertStringContainsString(
            'filename=licencies-2020-2021.xlsx',
            (string) $this->response()->headers->get('Content-Disposition'),
        );
    }

    /** Une licence seulement soumise/refusée n'est pas une adhésion : hors export. */
    public function testMembersWithoutAValidatedLicenseAreExcluded(): void
    {
        $this->actingAsSuperAdmin();
        $season = $this->currentSeason();
        $this->aMember()->named('Valide', 'Licencie')->licensedFor($season, LicenseStatus::VALIDEE)->persist();
        $this->aMember()->named('Soumis', 'Licencie')->licensedFor($season, LicenseStatus::SOUMISE)->persist();
        $this->aMember()->named('Refuse', 'Licencie')->licensedFor($season, LicenseStatus::REFUSEE)->persist();

        $this->getJson('/api/member/export?columns[]=firstName');

        $this->assertSame([['Valide']], array_slice($this->readExport(), 1));
    }

    public function testExportIsFilteredByTeam(): void
    {
        $this->actingAsSuperAdmin();
        $season = $this->currentSeason();
        $teamA = $this->aTeam()->named('Loisir 1')->persist();
        $teamB = $this->aTeam()->named('Compet 2')->persist();
        $this->aMember()->named('DansA', 'Licencie')->inTeams($teamA)->licensedFor($season)->persist();
        $this->aMember()->named('DansB', 'Licencie')->inTeams($teamB)->licensedFor($season)->persist();

        $this->getJson(sprintf('/api/member/export?teamId=%d&columns[]=firstName', $teamA->getId()));

        $this->assertSame([['DansA']], array_slice($this->readExport(), 1));
    }

    public function testExportIsFilteredByLicensePaid(): void
    {
        $this->actingAsSuperAdmin();
        $season = $this->currentSeason();
        $this->aMember()->named('Paye', 'Licencie')->licensedFor($season, LicenseStatus::PAYEE)->persist();
        $this->aMember()->named('Impaye', 'Licencie')->licensedFor($season, LicenseStatus::VALIDEE)->persist();

        $this->getJson('/api/member/export?licensePaid=true&columns[]=firstName');
        $this->assertSame([['Paye']], array_slice($this->readExport(), 1));

        $this->getJson('/api/member/export?licensePaid=false&columns[]=firstName');
        $this->assertSame([['Impaye']], array_slice($this->readExport(), 1));
    }

    public function testExportIsFilteredBySearch(): void
    {
        $this->actingAsSuperAdmin();
        $season = $this->currentSeason();
        $this->aMember()->named('Jean', 'Dupont')->licensedFor($season)->persist();
        $this->aMember()->named('Marc', 'Durand')->licensedFor($season)->persist();

        $this->getJson('/api/member/export?search=dupont&columns[]=firstName');

        $this->assertSame([['Jean']], array_slice($this->readExport(), 1));
    }

    /**
     * Une équipe inconnue ne fait pas d'erreur : l'export est simplement vide,
     * l'en-tête reste là pour que le fichier s'ouvre normalement.
     */
    public function testEmptyPopulationStillProducesAFileWithItsHeader(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/member/export?teamId=999999&columns[]=lastName&columns[]=firstName');

        $this->assertSame(200, $this->response()->getStatusCode());
        $this->assertSame([['Nom', 'Prénom']], $this->readExport());
    }

    /** Un membre apparaît une seule fois même s'il joue dans plusieurs équipes. */
    public function testMemberInSeveralTeamsIsExportedOnce(): void
    {
        $this->actingAsSuperAdmin();
        $teamA = $this->aTeam()->named('Loisir 1')->persist();
        $teamB = $this->aTeam()->named('Compet 2')->persist();
        $this->aMember()->named('Jean', 'Dupont')->inTeams($teamA, $teamB)->licensedFor($this->currentSeason())->persist();

        $this->getJson('/api/member/export?columns[]=firstName');

        $this->assertCount(2, $this->readExport());
    }

    public function testSoftDeletedMembersAreExcluded(): void
    {
        $this->actingAsSuperAdmin();
        $season = $this->currentSeason();
        $kept = $this->aMember()->named('Garde', 'Licencie')->licensedFor($season)->persist();
        $removed = $this->aMember()->named('Supprime', 'Licencie')->licensedFor($season)->persist();

        $this->deleteJson('/api/member/'.$removed->getId());
        $this->assertSame(200, $this->response()->getStatusCode());

        $this->getJson('/api/member/export?columns[]=firstName');

        $this->assertSame([['Garde']], array_slice($this->readExport(), 1));
        $this->assertNotNull($kept->getId());
    }

    // ── Pièces jointes (archive zip) ────────────────────────────────────────

    public function testExportWithoutFilesStaysAPlainSpreadsheet(): void
    {
        $this->actingAsSuperAdmin();
        $this->aMember()->named('Jean', 'Dupont')->licensedFor($this->currentSeason())->persist();

        $this->getJson('/api/member/export?columns[]=lastName');

        $this->assertStringContainsString('.xlsx', (string) $this->response()->headers->get('Content-Disposition'));
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $this->response()->headers->get('Content-Type'),
        );
    }

    public function testRequestingFilesReturnsAZipHoldingTheSheetAndThePieces(): void
    {
        $this->actingAsSuperAdmin();
        $season = $this->currentSeason();
        $member = $this->aMember()->named('Jean', 'Dupont')->licensedFor($season)->persist();
        $this->attachFile($member->getId(), 'license', $this->fakePdf('scan licence.pdf'));

        $this->getJson('/api/member/export?columns[]=lastName&files[]=license');

        $response = $this->response();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/zip', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            sprintf('filename=licencies-%s.zip', $season),
            (string) $response->headers->get('Content-Disposition'),
        );

        $this->assertSame([
            sprintf('licencies-%s.xlsx', $season),
            'pieces/Dupont Jean/Licence.pdf',
        ], $this->archiveEntries());
    }

    /** La pièce archivée est bien le fichier stocké, pas un placeholder. */
    public function testArchivedPieceKeepsItsContent(): void
    {
        $this->actingAsSuperAdmin();
        $member = $this->aMember()->named('Jean', 'Dupont')->licensedFor($this->currentSeason())->persist();
        $this->attachFile($member->getId(), 'license', $this->fakePdf());

        $this->getJson('/api/member/export?columns[]=lastName&files[]=license');

        $this->assertStringStartsWith('%PDF-1.4', $this->archiveContent('pieces/Dupont Jean/Licence.pdf'));
    }

    /** La photo d'identité vit dans un dossier racine, hors saison. */
    public function testIdentityPhotoIsExportedAlthoughItIsSeasonIndependent(): void
    {
        $this->actingAsSuperAdmin();
        $member = $this->aMember()->named('Zoé', 'Martin')->licensedFor($this->currentSeason())->persist();
        $this->attachFile($member->getId(), 'identity_photo', $this->fakePng('moi.png'));

        $this->getJson('/api/member/export?columns[]=lastName&files[]=identity_photo');

        $this->assertContains('pieces/Martin Zoé/Photo d\'identité.png', $this->archiveEntries());
    }

    public function testSeveralPieceKindsCanBeExportedTogether(): void
    {
        $this->actingAsSuperAdmin();
        $member = $this->aMember()->named('Jean', 'Dupont')->licensedFor($this->currentSeason())->persist();
        $this->attachFile($member->getId(), 'identity_photo', $this->fakePng());
        $this->attachFile($member->getId(), 'license', $this->fakePdf());
        $this->attachFile($member->getId(), 'medical_certificate', $this->fakePdf());

        $this->getJson(
            '/api/member/export?columns[]=lastName'
            .'&files[]=identity_photo&files[]=license&files[]=medical_certificate'
        );

        $entries = $this->archiveEntries();
        $this->assertContains('pieces/Dupont Jean/Photo d\'identité.png', $entries);
        $this->assertContains('pieces/Dupont Jean/Licence.pdf', $entries);
        $this->assertContains('pieces/Dupont Jean/Certificat médical.pdf', $entries);
    }

    /**
     * LE point sensible : un licencié qui a déposé une licence en 2020-2021 ET
     * une en saison courante ne doit recevoir que celle de la saison exportée.
     * On dépose dans les deux, on exporte chaque saison, et on vérifie que le
     * contenu livré est bien celui de la saison demandée — pas les deux, pas
     * l'autre.
     */
    public function testSeasonScopedPiecesFollowTheExportedSeasonOnly(): void
    {
        $this->actingAsSuperAdmin();
        $current = $this->currentSeason();
        $member = $this->aMember()->named('Jean', 'Dupont')->licensedFor($current)->persist();
        $this->alsoLicensedFor($member, '2020-2021');

        // Le dossier de saison n'est semé que pour la saison courante : on se
        // place sur l'ancienne saison le temps d'y déposer sa licence.
        $this->inSeason('2020-2021', fn () => $this->attachFileWithContent(
            $member->getId(), 'license', 'licence-2020',
        ));
        $this->attachFileWithContent($member->getId(), 'license', 'licence-courante');

        $this->getJson('/api/member/export?season=2020-2021&columns[]=lastName&files[]=license');
        $this->assertSame([
            'licencies-2020-2021.xlsx',
            'pieces/Dupont Jean/Licence.pdf',
        ], $this->archiveEntries());
        $this->assertStringContainsString('licence-2020', $this->archiveContent('pieces/Dupont Jean/Licence.pdf'));

        $this->getJson('/api/member/export?columns[]=lastName&files[]=license');
        $this->assertSame([
            sprintf('licencies-%s.xlsx', $current),
            'pieces/Dupont Jean/Licence.pdf',
        ], $this->archiveEntries());
        $this->assertStringContainsString('licence-courante', $this->archiveContent('pieces/Dupont Jean/Licence.pdf'));
    }

    /**
     * La photo d'identité, elle, n'est PAS datée : la médiathèque n'en stocke
     * qu'une par membre, dans un dossier racine hors saison. Elle sort donc
     * identique quelle que soit la saison exportée — et c'est voulu, il n'existe
     * pas de « photo de la saison » à choisir.
     */
    public function testIdentityPhotoIsTheSameWhicheverSeasonIsExported(): void
    {
        $this->actingAsSuperAdmin();
        $member = $this->aMember()->named('Jean', 'Dupont')->licensedFor($this->currentSeason())->persist();
        $this->alsoLicensedFor($member, '2020-2021');
        $this->attachFile($member->getId(), 'identity_photo', $this->fakePng());

        foreach ([$this->currentSeason(), '2020-2021'] as $season) {
            $this->getJson('/api/member/export?season='.$season.'&columns[]=lastName&files[]=identity_photo');

            $this->assertContains(
                'pieces/Dupont Jean/Photo d\'identité.png',
                $this->archiveEntries(),
                sprintf('La photo doit sortir aussi pour la saison %s', $season),
            );
        }
    }

    /** Un slot vide n'est pas une erreur : tout le monde n'a pas déposé sa pièce. */
    public function testMembersWithoutThePieceAreSimplySkipped(): void
    {
        $this->actingAsSuperAdmin();
        $season = $this->currentSeason();
        $withFile = $this->aMember()->named('Jean', 'Dupont')->licensedFor($season)->persist();
        $this->aMember()->named('Marc', 'Sansfichier')->licensedFor($season)->persist();
        $this->attachFile($withFile->getId(), 'license', $this->fakePdf());

        $this->getJson('/api/member/export?columns[]=lastName&files[]=license');

        $this->assertSame([
            sprintf('licencies-%s.xlsx', $season),
            'pieces/Dupont Jean/Licence.pdf',
        ], $this->archiveEntries());
    }

    /** Deux homonymes ne doivent pas écraser leurs pièces mutuellement. */
    public function testHomonymsGetSeparateFolders(): void
    {
        $this->actingAsSuperAdmin();
        $season = $this->currentSeason();
        $first = $this->aMember()->named('Jean', 'Dupont')->licensedFor($season)->persist();
        $second = $this->aMember()->named('Jean', 'Dupont')->licensedFor($season)->persist();
        $this->attachFile($first->getId(), 'license', $this->fakePdf());
        $this->attachFile($second->getId(), 'license', $this->fakePdf());

        $this->getJson('/api/member/export?columns[]=lastName&files[]=license');

        $entries = $this->archiveEntries();
        $this->assertContains('pieces/Dupont Jean/Licence.pdf', $entries);
        $this->assertContains('pieces/Dupont Jean (2)/Licence.pdf', $entries);
    }

    /** Les filtres de la liste valent aussi pour les pièces. */
    public function testPiecesFollowTheListFilters(): void
    {
        $this->actingAsSuperAdmin();
        $season = $this->currentSeason();
        $teamA = $this->aTeam()->named('Loisir 1')->persist();
        $teamB = $this->aTeam()->named('Compet 2')->persist();
        $inA = $this->aMember()->named('Jean', 'DansA')->inTeams($teamA)->licensedFor($season)->persist();
        $inB = $this->aMember()->named('Marc', 'DansB')->inTeams($teamB)->licensedFor($season)->persist();
        $this->attachFile($inA->getId(), 'license', $this->fakePdf());
        $this->attachFile($inB->getId(), 'license', $this->fakePdf());

        $this->getJson(sprintf('/api/member/export?teamId=%d&columns[]=lastName&files[]=license', $teamA->getId()));

        $this->assertSame([
            sprintf('licencies-%s.xlsx', $season),
            'pieces/DansA Jean/Licence.pdf',
        ], $this->archiveEntries());
    }

    /**
     * Le fichier a disparu de la zone entre la base et l'export : on saute la
     * pièce, l'archive reste livrable.
     */
    public function testAPieceMissingFromTheStorageZoneIsSkipped(): void
    {
        $this->actingAsSuperAdmin();
        $season = $this->currentSeason();
        $member = $this->aMember()->named('Jean', 'Dupont')->licensedFor($season)->persist();
        $this->attachFile($member->getId(), 'license', $this->fakePdf());
        FakeBunnyStorageClient::clear();

        $this->getJson('/api/member/export?columns[]=lastName&files[]=license');

        $this->assertSame(200, $this->response()->getStatusCode());
        $this->assertSame([sprintf('licencies-%s.xlsx', $season)], $this->archiveEntries());
    }

    /** Une zone HS doit échouer franchement, pas livrer une archive vide. */
    public function testAnUnavailableStorageZoneFailsTheExport(): void
    {
        $this->actingAsSuperAdmin();
        $member = $this->aMember()->named('Jean', 'Dupont')->licensedFor($this->currentSeason())->persist();
        $this->attachFile($member->getId(), 'license', $this->fakePdf());
        $this->configureBunny('');

        $this->getJson('/api/member/export?columns[]=lastName&files[]=license');

        $this->assertSame(502, $this->response()->getStatusCode());
    }

    /** Population vide + pièces demandées : une archive avec le seul tableau. */
    public function testEmptyPopulationStillProducesAnArchiveWithTheSheet(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/member/export?teamId=999999&columns[]=lastName&files[]=license');

        $this->assertSame(200, $this->response()->getStatusCode());
        $this->assertSame([sprintf('licencies-%s.xlsx', $this->currentSeason())], $this->archiveEntries());
    }

    public function testUnknownPieceIsRejected(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/member/export?columns[]=lastName&files[]=carte_vitale');

        $this->assertSame(422, $this->response()->getStatusCode());
    }

    // ── Outils ──────────────────────────────────────────────────────────────

    /**
     * Dépose un fichier dans un slot par défaut du membre, via l'API média — le
     * seeding de l'arbre se fait au premier GET, donc on passe par la route.
     *
     * Les dossiers des autres saisons sont écartés : un membre licencié
     * plusieurs saisons a un slot `license` par saison, et viser le premier
     * trouvé écraserait la pièce d'une autre année.
     */
    private function attachFile(int $memberId, string $systemKey, UploadedFile $file, ?string $season = null): void
    {
        $season ??= $this->currentSeason();

        $this->getJson('/api/member/'.$memberId.'/media');
        $tree = $this->assertJsonResponse(200);

        foreach ($tree as $root) {
            // season null = dossier racine hors saison (Identité).
            if ($root['season'] !== null && $root['season'] !== $season) {
                continue;
            }

            foreach ($root['children'] as $child) {
                if ($child['systemKey'] === $systemKey) {
                    $this->uploadFile('/api/member/'.$memberId.'/media/node/'.$child['id'].'/file', $file);
                    $this->assertJsonResponse(200);

                    return;
                }
            }
        }

        $this->fail(sprintf('Slot "%s" (saison %s) introuvable pour le membre %d', $systemKey, $season, $memberId));
    }

    /**
     * Ajoute une licence d'une autre saison à un membre déjà actif.
     * `LicenseBuilder` repasse le membre en PENDING_VALIDATION (il modélise une
     * demande entrante) — or l'export ne liste que les membres ACTIVE, donc on
     * le remet dans son état, sinon le membre disparaît de tout export.
     */
    private function alsoLicensedFor(Member $member, string $season): void
    {
        $this->aLicense()->forMember($member)->inSeason($season)
            ->withStatus(LicenseStatus::VALIDEE)->persist();

        $member->setStatus(MemberStatus::ACTIVE);
        $this->em()->flush();
    }

    /** Joue le callback en faisant croire à l'app que `$season` est la saison courante. */
    private function inSeason(string $season, callable $fn): void
    {
        $provider = static::getContainer()->get(SeasonProvider::class);
        $previous = $provider->current();
        $provider->set($season);

        try {
            $fn();
        } finally {
            $provider->set($previous);
        }
    }

    /** Dépose dans le slot un PDF dont le contenu est reconnaissable. */
    private function attachFileWithContent(int $memberId, string $systemKey, string $marker, ?string $season = null): void
    {
        $path = tempnam(sys_get_temp_dir(), 'test_pdf_');
        file_put_contents($path, "%PDF-1.4\n% ".$marker."\n%%EOF\n");

        $this->attachFile(
            $memberId,
            $systemKey,
            new UploadedFile($path, 'piece.pdf', 'application/pdf', test: true),
            $season,
        );
    }

    /**
     * Les chemins contenus dans l'archive renvoyée, triés — l'ordre d'écriture
     * dans un zip n'est pas ce qu'on teste.
     *
     * @return list<string>
     */
    private function archiveEntries(): array
    {
        $zip = $this->openArchive();
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $entries[] = (string) $zip->getNameIndex($i);
        }
        $zip->close();
        sort($entries);

        return $entries;
    }

    private function archiveContent(string $entry): string
    {
        $zip = $this->openArchive();
        $content = $zip->getFromName($entry);
        $zip->close();
        $this->assertNotFalse($content, sprintf('Entrée "%s" absente de l\'archive', $entry));

        return $content;
    }

    /** Le zip réellement envoyé, relu depuis les octets de la réponse. */
    private function openArchive(): \ZipArchive
    {
        $this->assertInstanceOf(BinaryFileResponse::class, $this->response());

        $path = tempnam(sys_get_temp_dir(), 'read_zip_').'.zip';
        file_put_contents($path, $this->client->getInternalResponse()->getContent());

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'La réponse n\'est pas une archive zip lisible');
        unlink($path);

        return $zip;
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

    /**
     * Le xlsx produit, relu ligne par ligne avec les cellules ramenées en
     * chaînes (comparaisons lisibles). `readExportCells()` garde les types.
     *
     * @return list<list<string>>
     */
    private function readExport(): array
    {
        return array_map(
            static fn (array $row) => array_map(
                static fn (mixed $cell) => $cell === null ? '' : (string) $cell,
                $row,
            ),
            $this->readExportCells(),
        );
    }

    /**
     * @return list<list<mixed>>
     */
    private function readExportCells(): array
    {
        $this->assertInstanceOf(
            BinaryFileResponse::class,
            $this->response(),
            'La réponse n\'est pas un fichier : '.$this->client->getInternalResponse()->getContent(),
        );

        // BrowserKit appelle sendContent() sur une BinaryFileResponse : le
        // fichier temporaire est déjà supprimé (deleteFileAfterSend). On relit
        // donc les octets réellement envoyés, ce qui prouve aussi qu'ils
        // forment bien un xlsx valide.
        $path = tempnam(sys_get_temp_dir(), 'read_export_').'.xlsx';
        file_put_contents($path, $this->client->getInternalResponse()->getContent());

        // SHOULD_PRESERVE_EMPTY_ROWS : sans ça le lecteur saute une ligne dont
        // toutes les cellules sont vides, et le test croirait le licencié absent.
        $reader = new Reader(new ReaderOptions(SHOULD_PRESERVE_EMPTY_ROWS: true));
        $reader->open($path);

        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $this->assertSame('Licenciés', $sheet->getName());
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            break;
        }
        $reader->close();
        unlink($path);

        return $rows;
    }
}
