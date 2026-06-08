<?php

namespace App\Application\UseCase\SalleClosure\DeleteSalleClosure;

use App\Common\Command\CommandInterface;

class DeleteSalleClosureCommand implements CommandInterface
{
    public function __construct(
        public readonly int $id,
    ) {
    }
}
