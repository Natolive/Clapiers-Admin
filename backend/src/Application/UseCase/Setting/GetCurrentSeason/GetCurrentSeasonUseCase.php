<?php

namespace App\Application\UseCase\Setting\GetCurrentSeason;

use App\Common\Command\CommandInterface;
use App\Common\Service\SeasonProvider;
use App\Common\UseCase\AbstractUseCase;

/**
 * Renvoie la saison courante retenue + la valeur suggérée (calculée par date).
 * Aucune commande : lecture pure.
 *
 * @extends AbstractUseCase<null>
 */
class GetCurrentSeasonUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly SeasonProvider $seasonProvider,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        return [
            'season' => $this->seasonProvider->current(),
            'suggestion' => $this->seasonProvider->computed(),
        ];
    }
}
