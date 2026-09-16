<?php

namespace App\Entity\Enum;

/**
 * Cycle de vie d'une demande de licence :
 * SOUMISE → VALIDEE → EN_PAIEMENT → PAYEE (ou REFUSEE / REMBOURSEE).
 */
enum LicenseStatus: string
{
    case SOUMISE = 'soumise';
    case VALIDEE = 'validee';
    case REFUSEE = 'refusee';
    case EN_PAIEMENT = 'en_paiement';
    case PAYEE = 'payee';
    case REMBOURSEE = 'remboursee';

    public function label(): string
    {
        return match ($this) {
            self::SOUMISE     => 'Soumise',
            self::VALIDEE     => 'Validée',
            self::REFUSEE     => 'Refusée',
            self::EN_PAIEMENT => 'En paiement',
            self::PAYEE       => 'Payée',
            self::REMBOURSEE  => 'Remboursée',
        };
    }

    /**
     * Statuts comptant comme adhésion active pour une saison : une licence
     * validée, en cours de paiement ou payée. Source unique pour toute stat ou
     * scope de saison (dashboard, liste des licenciés, membres d'une équipe).
     *
     * @return self[]
     */
    public static function activeMembership(): array
    {
        return [self::VALIDEE, self::EN_PAIEMENT, self::PAYEE];
    }

    /**
     * Les mêmes statuts en valeurs string, pour le SQL brut.
     *
     * @return string[]
     */
    public static function activeMembershipValues(): array
    {
        return array_map(static fn (self $s) => $s->value, self::activeMembership());
    }
}
