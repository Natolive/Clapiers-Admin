<?php

namespace App\Application\UseCase\Member\CreateUpdateMember;

use App\Validator\Season;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Licence à créer en même temps que la fiche (« Nouveau licencié »).
 *
 * Bloc absent = fiche seule, comme avant. Présent = licence de la saison,
 * directement VALIDEE : une fiche sans licence n'apparaît dans aucune liste,
 * toutes scopées à la saison, et l'admin qui saisit à la main n'a pas de
 * dossier à instruire.
 */
class NewMemberLicense
{
    public function __construct(
        /** Saison visée ; par défaut la saison courante. */
        #[Season]
        public readonly ?string $season = null,
        public readonly ?int $helloAssoTierId = null,
        /** Montant en centimes, figé comme à la validation d'une demande. */
        #[Assert\Positive]
        public readonly ?int $amount = null,
        /** Envoyer le mail « licence validée — voici le lien de paiement ». */
        public readonly bool $sendPaymentEmail = false,
    ) {
    }
}
