<?php

namespace App\Controller\Input;

use App\Validator\Season;

/**
 * Query string `?season=AAAA-AAAA` partagée par les endpoints filtrés par
 * saison dont la commande n'est pas mappable directement (paramètre de route
 * en plus, ou pas de commande). Mappée via #[MapQueryString].
 */
class SeasonQuery
{
    public function __construct(
        #[Season]
        public readonly ?string $season = null,
    ) {
    }
}
