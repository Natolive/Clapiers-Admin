<?php

namespace App\Entity\Enum;

enum GameHistoryAction: string
{
    case CREATED = 'created';
    case UPDATED = 'updated';
    case DELETED = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Création',
            self::UPDATED => 'Modification',
            self::DELETED => 'Suppression',
        };
    }
}
