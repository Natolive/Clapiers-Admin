<?php

namespace App\Application\UseCase\Team\CreateUpdateTeam;

use App\Common\Command\CommandInterface;
use Symfony\Component\Validator\Constraints as Assert;

class CreateUpdateTeamCommand implements CommandInterface
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $name,
        public readonly ?int $id = null,
    ) {
    }
}
