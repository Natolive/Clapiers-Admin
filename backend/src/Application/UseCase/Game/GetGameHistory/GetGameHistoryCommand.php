<?php

namespace App\Application\UseCase\Game\GetGameHistory;

use App\Common\Command\CommandInterface;

class GetGameHistoryCommand implements CommandInterface
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $limit = 20,
        public readonly ?int $gameId = null,
        public readonly ?int $teamId = null,
    ) {
    }
}
