<?php

namespace App\Application\UseCase\Setting\SetBunnyConfig;

use App\Common\Command\CommandInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Champs omis (ou vides) = inchangés. Un secret vide signifie « garder celui
 * déjà enregistré » : le formulaire ne les réaffiche jamais.
 */
class SetBunnyConfigCommand implements CommandInterface
{
    public function __construct(
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Url(requireTld: true)]
        #[Assert\Length(max: 255)]
        public readonly ?string $storageUrl = null,

        #[Assert\Length(max: 255)]
        public readonly ?string $storageKey = null,

        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Url(requireTld: true)]
        #[Assert\Length(max: 255)]
        public readonly ?string $cdnUrl = null,

        #[Assert\Length(max: 255)]
        public readonly ?string $tokenKey = null,
    ) {
    }
}
