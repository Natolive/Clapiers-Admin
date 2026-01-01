<?php

namespace App\Application\UseCase\Member\GetMembersByTeam;

use App\Common\Command\CommandInterface;
use Symfony\Component\Validator\Constraints as Assert;

class GetMembersByTeamCommand implements CommandInterface
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        public readonly int $teamId
    ) {
    }
}
