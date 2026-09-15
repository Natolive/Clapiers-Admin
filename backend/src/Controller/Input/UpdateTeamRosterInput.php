<?php

namespace App\Controller\Input;

use App\Validator\Season;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corps de PATCH /api/team/{id}/members : ajouts et retraits de licenciés.
 * L'id de l'équipe vient de la route, d'où ce DTO plutôt qu'un mapping direct
 * dans la commande.
 */
class UpdateTeamRosterInput
{
    /**
     * @param list<int> $add
     * @param list<int> $remove
     */
    public function __construct(
        #[Assert\All([new Assert\Type('integer')])]
        public readonly array $add = [],
        #[Assert\All([new Assert\Type('integer')])]
        public readonly array $remove = [],
        #[Season]
        public readonly ?string $season = null,
    ) {
    }
}
