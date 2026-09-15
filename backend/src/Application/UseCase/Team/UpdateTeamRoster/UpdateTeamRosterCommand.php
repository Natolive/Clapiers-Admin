<?php

namespace App\Application\UseCase\Team\UpdateTeamRoster;

use App\Common\Command\CommandInterface;

class UpdateTeamRosterCommand implements CommandInterface
{
    /**
     * @param list<int> $addMemberIds    licenciés à rattacher à l'équipe
     * @param list<int> $removeMemberIds licenciés à en détacher
     * @param string|null $season        saison de l'effectif renvoyé (null = courante)
     */
    public function __construct(
        public readonly int $teamId,
        public readonly array $addMemberIds = [],
        public readonly array $removeMemberIds = [],
        public readonly ?string $season = null,
    ) {
    }
}
