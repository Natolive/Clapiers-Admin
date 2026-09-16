<?php

namespace App\Application\UseCase\Member\ExportMembers;

use App\Common\Command\CommandInterface;
use App\Entity\MemberMediaDefaults;
use App\Validator\Season;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Export des licenciés. Les quatre premiers champs sont exactement les filtres
 * de la liste paginée (même repository) : on exporte ce qu'on voit à l'écran.
 * `columns` choisit les colonnes du fichier ; absent = toutes.
 * `files` choisit les pièces de la médiathèque à joindre ; absent = aucune,
 * et l'export reste alors un simple xlsx.
 */
class ExportMembersCommand implements CommandInterface
{
    /**
     * @param string[]|null $columns
     * @param string[]|null $files
     */
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?int $teamId = null,
        public readonly ?bool $licensePaid = null,
        #[Season]
        public readonly ?string $season = null,
        #[Assert\Count(min: 1, minMessage: 'Sélectionnez au moins une colonne à exporter.')]
        #[Assert\All([
            new Assert\Choice(callback: [MemberExportColumn::class, 'values'], message: 'Colonne inconnue : {{ value }}.'),
        ])]
        public readonly ?array $columns = null,
        #[Assert\All([
            new Assert\Choice(callback: [MemberMediaDefaults::class, 'documentSlotKeys'], message: 'Pièce inconnue : {{ value }}.'),
        ])]
        public readonly ?array $files = null,
    ) {
    }

    /**
     * Les pièces demandées, dédoublonnées et dans l'ordre du mapping par défaut.
     * Vide = export xlsx simple, sans archive.
     *
     * @return list<string>
     */
    public function selectedFiles(): array
    {
        if ($this->files === null) {
            return [];
        }

        return array_values(array_filter(
            MemberMediaDefaults::documentSlotKeys(),
            fn (string $key) => in_array($key, $this->files, true),
        ));
    }

    /**
     * Les colonnes demandées, dans l'ordre de l'enum (pas celui de la requête) :
     * un fichier d'export a toujours la même tête, quel que soit l'ordre des
     * cases cochées. Aucune sélection = tout.
     *
     * @return list<MemberExportColumn>
     */
    public function selectedColumns(): array
    {
        if ($this->columns === null) {
            return MemberExportColumn::all();
        }

        return array_values(array_filter(
            MemberExportColumn::all(),
            fn (MemberExportColumn $column) => in_array($column->value, $this->columns, true),
        ));
    }
}
