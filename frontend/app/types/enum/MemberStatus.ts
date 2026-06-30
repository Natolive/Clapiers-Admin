export enum MemberStatus {
    PENDING_VALIDATION = 'pending_validation',
    ACTIVE             = 'active',
    REJECTED           = 'rejected',
}

export const MemberStatusLabels: Record<MemberStatus, string> = {
    [MemberStatus.PENDING_VALIDATION]: 'En attente de validation',
    [MemberStatus.ACTIVE]:             'Actif',
    [MemberStatus.REJECTED]:           'Refusé',
};
