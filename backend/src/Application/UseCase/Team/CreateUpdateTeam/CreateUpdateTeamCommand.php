<?php

namespace App\Application\UseCase\Team\CreateUpdateTeam;

use App\Common\Command\CommandInterface;
use Symfony\Component\Validator\Constraints as Assert;

class CreateUpdateTeamCommand implements CommandInterface
{
    /**
     * @param list<int>|null $userIds Coachs de l'équipe (null = ne pas modifier ;
     *                                [] = retirer tous les coachs)
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $name,
        public readonly ?int $id = null,
        #[Assert\All([new Assert\Type('integer')])]
        public readonly ?array $userIds = null,
    ) {
    }
}
