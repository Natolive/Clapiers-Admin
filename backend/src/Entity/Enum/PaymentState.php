<?php

namespace App\Entity\Enum;

/**
 * État d'un paiement, aligné sur les états remontés par HelloAsso.
 */
enum PaymentState: string
{
    case AUTHORIZED = 'authorized';
    case REFUSED = 'refused';
    case REFUNDED = 'refunded';
    case WAITING = 'waiting';
}
