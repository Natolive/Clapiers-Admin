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
 * et l'export reste alors un simple xlsx. `memberIds` restreint aux lignes
 * cochées à l'écran, en plus des filtres — jamais à leur place.
 */
class ExportMembersCommand implements CommandInterface
{
    /**
     * @param array<int|string>|null $memberIds
     * @param string[]|null $columns
     * @param string[]|null $files
     */
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?int $teamId = null,
        public readonly ?bool $licensePaid = null,
        #[Season]
        public readonly ?string $season = null,
        /** null = inscrits et non inscrits confondus. */
        public readonly ?bool $fsgtRegistered = null,
        /**
         * Lignes cochées dans la liste ; null = toute la population filtrée.
         * Reçus en query string, donc des chaînes : on valide qu'elles sont
         * bien numériques et la conversion se fait dans selectedMemberIds().
         */
        #[Assert\All([
            new Assert\Type(type: ['integer', 'digit'], message: 'Identifiant de licencié invalide : {{ value }}.'),
        ])]
        public readonly ?array $memberIds = null,
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
     * Les lignes cochées, en entiers. null = pas de sélection, donc toute la
     * population filtrée.
     *
     * @return list<int>|null
     */
    public function selectedMemberIds(): ?array
    {
        if ($this->memberIds === null) {
            return null;
        }

        return array_values(array_map(intval(...), $this->memberIds));
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
