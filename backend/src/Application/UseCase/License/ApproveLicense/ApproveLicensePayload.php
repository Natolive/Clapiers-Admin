<?php

namespace App\Application\UseCase\License\ApproveLicense;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corps de la requête d'approbation (le tarif choisi par l'admin parmi les
 * tiers HelloAsso, et le montant correspondant en centimes).
 */
class ApproveLicensePayload
{
    public function __construct(
        #[Assert\Positive]
        public readonly int $helloAssoTierId,
        #[Assert\Positive]
        public readonly int $amount,
    ) {
    }
}
