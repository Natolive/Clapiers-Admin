<?php

namespace App\Application\UseCase\Member\ExportMembers;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\MemberMediaStorage;
use App\Common\Service\SeasonProvider;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\License;
use App\Entity\Member;
use App\Entity\MemberDocument;
use App\Entity\MemberMediaDefaults;
use App\Repository\LicenseRepository;
use App\Repository\MemberDocumentRepository;
use App\Repository\MemberRepository;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Export des licenciés : la population et les filtres sont ceux de la liste
 * (même repository), les colonnes sont choisies par l'appelant.
 *
 * Deux formes de sortie, selon que des pièces sont demandées ou non :
 *  - aucune pièce  → le xlsx seul ;
 *  - des pièces    → une archive zip contenant le xlsx et un dossier par
 *                    licencié avec ses pièces (photo d'identité, licence…).
 *
 * `run()` renvoie un fichier, pas du JSON : le contrôleur l'appelle directement
 * et mappe les erreurs à la main (cf. DownloadMyTeamMemberLicenseUseCase).
 *
 * @extends AbstractUseCase<ExportMembersCommand>
 */
class ExportMembersUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly LicenseRepository $licenseRepository,
        private readonly MemberDocumentRepository $documentRepository,
        private readonly MemberMediaStorage $storage,
        private readonly SeasonProvider $seasonProvider,
    ) {
    }

    public function run(?CommandInterface $command = null): Response
    {
        if (!$command instanceof ExportMembersCommand) {
            throw new UseCaseException('Invalid command');
        }

        // Au moins une colonne valide est garantie par la validation de la
        // commande (Count + Choice), donc pas de garde « zéro colonne » ici.
        $columns = $command->selectedColumns();
        $files = $command->selectedFiles();

        // Même défaut que la liste des licenciés : la saison courante.
        $season = $command->season ?: $this->seasonProvider->current();

        $members = $this->memberRepository->findForExport(
            $command->search,
            $command->teamId,
            $command->licensePaid,
            $season,
            $command->fsgtRegistered,
            $command->selectedMemberIds(),
        );

        $memberIds = array_map(static fn (Member $m) => (int) $m->getId(), $members);
        $licenses = $this->licenseRepository->findBySeasonIndexedByMember($memberIds, $season);

        $sheet = $this->writeSheet($columns, $members, $licenses);

        if ($files === []) {
            return $this->download($sheet, sprintf('licencies-%s.xlsx', $season), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }

        $archive = $this->writeArchive($sheet, $season, $members, $files);
        unlink($sheet);

        return $this->download($archive, sprintf('licencies-%s.zip', $season), 'application/zip');
    }

    private function download(string $path, string $fileName, string $contentType): BinaryFileResponse
    {
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $contentType);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $fileName);
        // Fichier temporaire : il ne survit pas à la réponse.
        $response->deleteFileAfterSend(true);

        return $response;
    }

    /**
     * @param list<MemberExportColumn> $columns
     * @param Member[]                 $members
     * @param array<int, License>      $licenses
     *
     * @return string chemin du fichier xlsx généré
     */
    private function writeSheet(array $columns, array $members, array $licenses): string
    {
        $path = tempnam(sys_get_temp_dir(), 'export_membres_').'.xlsx';

        $writer = new Writer();
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Licenciés');

        $writer->addRow(Row::fromValuesWithStyle(
            array_map(static fn (MemberExportColumn $c) => $c->label(), $columns),
            new Style(fontBold: true),
        ));

        foreach ($members as $member) {
            $license = $licenses[$member->getId()] ?? null;

            $writer->addRow(Row::fromValues(array_map(
                static fn (MemberExportColumn $c) => $c->value($member, $license),
                $columns,
            )));
        }

        $writer->close();

        return $path;
    }

    /**
     * Archive zip : le tableau à la racine, puis `pieces/<Nom Prénom>/<Pièce>.<ext>`.
     * Un slot vide est simplement absent — tout le monde n'a pas déposé son
     * certificat, ce n'est pas une erreur. En revanche une zone de stockage HS
     * fait remonter son 502 : mieux vaut un export en échec qu'une archive
     * silencieusement vide.
     *
     * ponytail: les pièces transitent par des fichiers temporaires (addFile, pas
     * addFromString) pour ne pas garder toute l'archive en mémoire ; plafond =
     * l'espace disque du pod. Si un club dépasse ça, passer à une génération
     * asynchrone avec envoi d'un lien.
     *
     * @param Member[]     $members
     * @param list<string> $files
     *
     * @return string chemin de l'archive générée
     */
    private function writeArchive(string $sheetPath, string $season, array $members, array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'export_membres_').'.zip';

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new UseCaseException("Impossible de créer l'archive d'export.", Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $zip->addFile($sheetPath, sprintf('licencies-%s.xlsx', $season));

        $slots = MemberMediaDefaults::documentSlots();
        $byMember = $this->documentRepository->findDefaultSlotsForMembers(
            array_map(static fn (Member $m) => (int) $m->getId(), $members),
            $season,
            $files,
        );

        // Les fichiers temporaires ne peuvent être supprimés qu'après close() :
        // ZipArchive ne les lit qu'à ce moment-là.
        $temporaries = [];
        $usedFolders = [];

        foreach ($members as $member) {
            $folder = $this->uniqueFolder($member, $usedFolders);

            foreach ($files as $key) {
                $node = $byMember[$member->getId()][$key] ?? null;
                if ($node === null || !$node->hasFile()) {
                    continue;
                }

                $content = $this->storage->contents((string) $node->getStoredName());
                if ($content === null) {
                    continue;
                }

                $temporary = tempnam(sys_get_temp_dir(), 'export_piece_');
                file_put_contents($temporary, $content);
                $temporaries[] = $temporary;

                $zip->addFile($temporary, sprintf('pieces/%s/%s', $folder, $this->fileName($slots[$key]['label'], $node)));
            }
        }

        $zip->close();

        foreach ($temporaries as $temporary) {
            unlink($temporary);
        }

        return $path;
    }

    /**
     * « Nom Prénom » assaini, suffixé au besoin : deux homonymes ne doivent pas
     * voir leurs pièces atterrir dans le même dossier.
     *
     * @param array<string, int> $used
     */
    private function uniqueFolder(Member $member, array &$used): string
    {
        $base = $this->sanitize($member->getLastName().' '.$member->getFirstName());
        $base = $base !== '' ? $base : 'licencie';

        $used[$base] = ($used[$base] ?? 0) + 1;

        return $used[$base] === 1 ? $base : sprintf('%s (%d)', $base, $used[$base]);
    }

    /** Le libellé du slot, avec l'extension du fichier d'origine. */
    private function fileName(string $label, MemberDocument $node): string
    {
        $extension = pathinfo((string) $node->getOriginalName(), PATHINFO_EXTENSION);

        return $this->sanitize($label).($extension !== '' ? '.'.strtolower($extension) : '');
    }

    /** Retire ce qui n'a rien à faire dans un nom de fichier ou de dossier. */
    private function sanitize(string $value): string
    {
        return trim((string) preg_replace('#[/\\\\:*?"<>|]+#', ' ', $value));
    }
}
