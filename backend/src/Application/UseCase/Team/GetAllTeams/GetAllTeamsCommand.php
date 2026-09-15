<?php

namespace App\Application\UseCase\Team\GetAllTeams;

use App\Common\Command\CommandInterface;

class GetAllTeamsCommand implements CommandInterface
{
    /**
     * @param string|null $season saison des compteurs d'effectif (null = saison courante)
     */
    public function __construct(
        public readonly ?string $season = null,
    ) {
    }
}
