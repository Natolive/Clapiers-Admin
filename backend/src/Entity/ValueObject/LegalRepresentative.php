<?php

namespace App\Entity\ValueObject;

use Doctrine\ORM\Mapping as ORM;

/**
 * Représentant légal d'un membre mineur, renseigné à l'inscription.
 * Champs vides ('') quand le membre est majeur (aucun représentant).
 */
#[ORM\Embeddable]
class LegalRepresentative
{
    #[ORM\Column(length: 255)]
    public string $firstName = '';

    #[ORM\Column(length: 255)]
    public string $lastName = '';

    #[ORM\Column(length: 255)]
    public string $email = '';

    #[ORM\Column(length: 30)]
    public string $phone = '';

    public function __construct(string $firstName = '', string $lastName = '', string $email = '', string $phone = '')
    {
        $this->firstName = $firstName;
        $this->lastName  = $lastName;
        $this->email     = $email;
        $this->phone     = $phone;
    }

    public function toArray(): array
    {
        return [
            'firstName' => $this->firstName,
            'lastName'  => $this->lastName,
            'email'     => $this->email,
            'phone'     => $this->phone,
        ];
    }
}
