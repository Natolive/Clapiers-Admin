<?php

namespace App\Entity;

/**
 * Source unique du "mapping par défaut" de la médiathèque. Deux familles :
 *  - des dossiers racine indépendants de la saison (ex. Identité → photo, pièce
 *    d'identité), toujours présents ;
 *  - des documents pré-créés dans chaque dossier de saison (licence, certificat).
 * Enrichir le mapping = ajouter une entrée ici, rien d'autre à toucher.
 */
final class MemberMediaDefaults
{
    /** Clé système d'un dossier de saison (premier niveau, daté). */
    public const SEASON_KEY = 'season';

    /**
     * Dossiers racine indépendants de la saison.
     * systemKey => [label, documents: systemKey => label].
     */
    public const ROOT_FOLDERS = [
        'identity' => [
            'label' => 'Identité',
            'documents' => [
                'identity_photo' => "Photo d'identité",
                'id_card' => "Pièce d'identité",
            ],
        ],
    ];

    /** Documents par défaut créés dans chaque dossier de saison. */
    public const SEASON_DOCUMENTS = [
        'license' => 'Licence',
        'medical_certificate' => 'Certificat médical',
        'attestation' => "Attestation sur l'honneur",
    ];

    /**
     * Les deux familles à plat : clé système => [label, seasonScoped]. Dérivé
     * des constantes ci-dessus, donc un nouveau slot par défaut devient
     * exportable sans rien toucher d'autre — la promesse du fichier tient.
     *
     * `seasonScoped` dit où chercher le nœud : dans le dossier de la saison
     * demandée, ou dans un dossier racine indépendant de la saison.
     *
     * @return array<string, array{label: string, seasonScoped: bool}>
     */
    public static function documentSlots(): array
    {
        $slots = [];

        foreach (self::ROOT_FOLDERS as $folder) {
            foreach ($folder['documents'] as $key => $label) {
                $slots[$key] = ['label' => $label, 'seasonScoped' => false];
            }
        }

        foreach (self::SEASON_DOCUMENTS as $key => $label) {
            $slots[$key] = ['label' => $label, 'seasonScoped' => true];
        }

        return $slots;
    }

    /**
     * Cible du `Assert\Choice` sur les pièces demandées à l'export.
     *
     * @return list<string>
     */
    public static function documentSlotKeys(): array
    {
        return array_keys(self::documentSlots());
    }
}
