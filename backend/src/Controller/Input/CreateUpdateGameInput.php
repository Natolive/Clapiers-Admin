<?php

namespace App\Controller\Input;

use App\Entity\Enum\GameVenue;
use Symfony\Component\Validator\Constraints as Assert;

class CreateUpdateGameInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string    $opponent = '',

        #[Assert\NotBlank]
        public readonly string    $date = '',

        #[Assert\Length(max: 10)]
        public readonly ?string   $meetingTime = null,

        public readonly GameVenue $venue = GameVenue::HOME,

        #[Assert\Length(max: 255)]
        public readonly ?string   $location = null,

        public readonly ?int      $teamId = null,
    ) {
    }
}
