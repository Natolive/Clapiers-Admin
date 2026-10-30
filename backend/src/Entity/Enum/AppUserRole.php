<?php

namespace App\Entity\Enum;

enum AppUserRole: string
{
    // Role values as constants for use in attributes
    public const ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';
    public const ROLE_COACH = 'ROLE_COACH';
    public const ROLE_USER = 'ROLE_USER';
    public const ROLE_VIEW_MESSAGE = 'ROLE_VIEW_MESSAGE';

    // Enum cases
    case SUPER_ADMIN = self::ROLE_SUPER_ADMIN;
    case COACH = self::ROLE_COACH;
    case USER = self::ROLE_USER;
    case VIEW_MESSAGE = self::ROLE_VIEW_MESSAGE;

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::COACH => "Chef d'équipe",
            self::USER => 'Utilisateur',
            self::VIEW_MESSAGE => 'Voir messages',
        };
    }

    /**
     * Get all roles that this role inherits
     * @return AppUserRole[]
     */
    public function inheritedRoles(): array
    {
        return match ($this) {
            self::SUPER_ADMIN => [self::SUPER_ADMIN, self::COACH, self::USER, self::VIEW_MESSAGE],
            self::COACH => [self::COACH, self::USER],
            self::VIEW_MESSAGE => [self::VIEW_MESSAGE, self::USER],
            self::USER => [self::USER],
        };
    }
}
