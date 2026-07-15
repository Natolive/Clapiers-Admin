<?php

namespace App\Entity\Enum;

/**
 * Statut d'un membre. Les membres créés par un admin sont ACTIVE ;
 * une demande de licence publique naît PENDING_VALIDATION jusqu'à validation.
 */
enum MemberStatus: string
{
    case PENDING_VALIDATION = 'pending_validation';
    case ACTIVE = 'active';
    case REJECTED = 'rejected';
}
