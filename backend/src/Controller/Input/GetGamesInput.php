<?php

namespace App\Controller\Input;

class GetGamesInput
{
    public function __construct(
        public readonly ?int    $teamId = null,
        public readonly ?string $start = null,
        public readonly ?string $end = null,
    ) {
    }
}
