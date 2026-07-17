<?php

namespace App\Application\UseCase\Setting\GetInscriptionsStatus;

use App\Common\Command\CommandInterface;
use App\Common\Service\InscriptionsStatusProvider;
use App\Common\UseCase\AbstractUseCase;

/**
 * Renvoie le statut d'ouverture des inscriptions. Aucune commande : lecture pure.
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
        return ['open' => $this->provider->isOpen()];
    }
}
