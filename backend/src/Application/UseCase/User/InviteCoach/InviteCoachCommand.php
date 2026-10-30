<?php

namespace App\Application\UseCase\User\InviteCoach;

use App\Common\Command\CommandInterface;
use Symfony\Component\Validator\Constraints as Assert;

class InviteCoachCommand implements CommandInterface
{
    /**
     * @param list<int> $teamIds équipes dont le licencié sera responsable
     */
    public function __construct(
        #[Assert\Positive]
        public readonly int $memberId,
        #[Assert\Count(min: 1, minMessage: 'Choisissez au moins une équipe.')]
        #[Assert\All([new Assert\Type('int')])]
        public readonly array $teamIds,
    ) {
    }
}
