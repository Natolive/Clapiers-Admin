<?php

namespace App\Application\UseCase\Setting\GetInscriptionsStatus;

use App\Common\Command\CommandInterface;
use App\Common\Service\InscriptionsStatusProvider;
use App\Common\UseCase\AbstractUseCase;

/**
 * Renvoie le statut des inscriptions : `open` (affichage indicatif) et
 * `formOpen` (réception réelle des demandes). Aucune commande : lecture pure.
 *
 * @extends AbstractUseCase<null>
 */
class GetInscriptionsStatusUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly InscriptionsStatusProvider $provider,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        return [
            'open' => $this->provider->isOpen(),
            'formOpen' => $this->provider->isFormOpen(),
        ];
    }
}
