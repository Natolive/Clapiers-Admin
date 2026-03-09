<?php

namespace App\Entity\Enum;

enum GameVenue: string
{
    case HOME = 'home';
    case AWAY = 'away';

    public function label(): string
    {
        return match ($this) {
            self::HOME => 'Domicile',
            self::AWAY => 'Extérieur',
        };
    }
}
