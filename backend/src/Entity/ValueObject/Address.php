<?php

namespace App\Entity\ValueObject;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class Address
{
    #[ORM\Column(length: 255)]
    public string $street = '';

    #[ORM\Column(length: 10)]
    public string $zip = '';

    #[ORM\Column(length: 100)]
    public string $city = '';

    public function __construct(string $street = '', string $zip = '', string $city = '')
    {
        $this->street = $street;
        $this->zip    = $zip;
        $this->city   = $city;
    }

    public function toArray(): array
    {
        return [
            'street' => $this->street,
            'zip'    => $this->zip,
            'city'   => $this->city,
        ];
    }
}
