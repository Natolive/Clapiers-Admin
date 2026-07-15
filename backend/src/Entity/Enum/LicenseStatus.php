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
}
