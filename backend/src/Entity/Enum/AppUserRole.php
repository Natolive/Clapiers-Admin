<?php

namespace App\Entity\Enum;

enum AppUserRole: string
{
    // Role values as constants for use in attributes
    public const ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_USER = 'ROLE_USER';
    public const ROLE_VIEW_MESSAGE = 'ROLE_VIEW_MESSAGE';
    public const ROLE_CONFIRM_MESSAGE = 'ROLE_CONFIRM_MESSAGE';

    // Enum cases
    case SUPER_ADMIN = self::ROLE_SUPER_ADMIN;
    case ADMIN = self::ROLE_ADMIN;
    case USER = self::ROLE_USER;
    case VIEW_MESSAGE = self::ROLE_VIEW_MESSAGE;
    case CONFIRM_MESSAGE = self::ROLE_CONFIRM_MESSAGE;

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN => 'Admin',
            self::USER => 'Utilisateur',
            self::VIEW_MESSAGE => 'Voir messages',
            self::CONFIRM_MESSAGE => 'Gérer messages',
        };
    }

    /**
     * Get all roles that this role inherits
     * @return AppUserRole[]
     */
    public function inheritedRoles(): array
    {
        return match ($this) {
            self::SUPER_ADMIN => [self::SUPER_ADMIN, self::ADMIN, self::USER, self::VIEW_MESSAGE, self::CONFIRM_MESSAGE],
            self::ADMIN => [self::ADMIN, self::USER],
            self::CONFIRM_MESSAGE => [self::CONFIRM_MESSAGE, self::VIEW_MESSAGE, self::USER],
            self::VIEW_MESSAGE => [self::VIEW_MESSAGE, self::USER],
            self::USER => [self::USER],
        };
    }
}
