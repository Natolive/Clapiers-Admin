export enum LicenseStatus {
    SOUMISE     = 'soumise',
    VALIDEE     = 'validee',
    REFUSEE     = 'refusee',
    EN_PAIEMENT = 'en_paiement',
    PAYEE       = 'payee',
    REMBOURSEE  = 'remboursee',
}

export const LicenseStatusLabels: Record<LicenseStatus, string> = {
    [LicenseStatus.SOUMISE]:     'Soumise',
    [LicenseStatus.VALIDEE]:     'Validée',
    [LicenseStatus.REFUSEE]:     'Refusée',
    [LicenseStatus.EN_PAIEMENT]: 'En paiement',
    [LicenseStatus.PAYEE]:       'Payée',
    [LicenseStatus.REMBOURSEE]:  'Remboursée',
};
