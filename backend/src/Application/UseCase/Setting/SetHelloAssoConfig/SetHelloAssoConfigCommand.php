<?php

namespace App\Application\UseCase\Setting\SetHelloAssoConfig;

use App\Common\Command\CommandInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Champs omis (ou vides) = inchangés. Un secret client vide signifie « garder
 * celui déjà enregistré » : le formulaire ne le réaffiche jamais.
 */
class SetHelloAssoConfigCommand implements CommandInterface
{
    public function __construct(
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Url(requireTld: true)]
        #[Assert\Length(max: 255)]
        public readonly ?string $baseUrl = null,

        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Length(max: 255)]
        public readonly ?string $clientId = null,

        #[Assert\Length(max: 255)]
        public readonly ?string $clientSecret = null,

        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Length(max: 255)]
        public readonly ?string $organizationSlug = null,

        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Length(max: 50)]
        public readonly ?string $membershipFormType = null,

        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Length(max: 255)]
        public readonly ?string $membershipFormSlug = null,
    ) {
    }
}
