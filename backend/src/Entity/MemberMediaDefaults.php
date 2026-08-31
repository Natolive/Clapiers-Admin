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
}
