<?php

namespace App\Application\UseCase\Setting\SetInscriptionsStatus;

use App\Common\Command\CommandInterface;
use Symfony\Component\Validator\Constraints as Assert;

class SetInscriptionsStatusCommand implements CommandInterface
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Type('bool')]
        public readonly bool $open,
    ) {
    }
}
