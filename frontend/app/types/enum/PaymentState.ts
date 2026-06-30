export enum PaymentState {
    AUTHORIZED = 'authorized',
    REFUSED    = 'refused',
    REFUNDED   = 'refunded',
    WAITING    = 'waiting',
}

export const PaymentStateLabels: Record<PaymentState, string> = {
    [PaymentState.AUTHORIZED]: 'Autorisé',
    [PaymentState.REFUSED]:    'Refusé',
    [PaymentState.REFUNDED]:   'Remboursé',
    [PaymentState.WAITING]:    'En attente',
};
