<?php

namespace App\Application\UseCase\Setting\SetInscriptionsStatus;

use App\Common\Command\CommandInterface;
use Symfony\Component\Validator\Constraints as Assert;

class SetInscriptionsStatusCommand implements CommandInterface
{
    public function __construct(
        /** Affichage indicatif sur le site public. */
        #[Assert\Type('bool')]
        public readonly ?bool $open = null,

        /** Réception réelle des demandes de licence. */
        #[Assert\Type('bool')]
        public readonly ?bool $formOpen = null,
    ) {
    }
}
