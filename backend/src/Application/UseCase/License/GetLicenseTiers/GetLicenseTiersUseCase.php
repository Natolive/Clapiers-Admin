<?php

namespace App\Application\UseCase\License\GetLicenseTiers;

use App\Common\Command\CommandInterface;
use App\Common\Service\HelloAsso\HelloAssoClientInterface;
use App\Common\UseCase\AbstractUseCase;

/**
 * Renvoie les tarifs (tiers) du formulaire d'adhésion HelloAsso, pour le
 * menu déroulant de la validation. Aucune commande : lecture pure.
 *
 * @extends AbstractUseCase<null>
 */
class GetLicenseTiersUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly HelloAssoClientInterface $helloAssoClient,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        return $this->helloAssoClient->getFormTiers();
    }
}
