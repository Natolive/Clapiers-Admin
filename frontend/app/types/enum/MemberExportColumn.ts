/**
 * Colonnes proposées à l'export des licenciés. Miroir de l'enum PHP
 * `MemberExportColumn` : les valeurs partent telles quelles en `?columns[]=`,
 * le back refuse (422) toute valeur qu'il ne connaît pas.
 */
export enum MemberExportColumn {
    LAST_NAME = 'lastName',
    FIRST_NAME = 'firstName',
    GENDER = 'gender',
    BIRTH_DATE = 'birthDate',
    AGE = 'age',
    NATIONALITY = 'nationality',
    STATUS = 'status',
    EMAIL = 'email',
    PHONE_NUMBER = 'phoneNumber',
    ADDRESS_STREET = 'addressStreet',
    ADDRESS_ZIP = 'addressZip',
    ADDRESS_CITY = 'addressCity',
    TEAMS = 'teams',
    LICENSE_NUMBER = 'licenseNumber',
    LICENSE_STATUS = 'licenseStatus',
    LICENSE_PAID = 'licensePaid',
    LICENSE_AMOUNT = 'licenseAmount',
    LICENSE_APPROVED_AT = 'licenseApprovedAt',
    HEALTH_DECLARATION = 'healthDeclaration',
    LEGAL_REP_FIRST_NAME = 'legalRepFirstName',
    LEGAL_REP_LAST_NAME = 'legalRepLastName',
    LEGAL_REP_EMAIL = 'legalRepEmail',
    LEGAL_REP_PHONE = 'legalRepPhone',
    CREATED_AT = 'createdAt',
}

export const MemberExportColumnLabels: Record<MemberExportColumn, string> = {
    [MemberExportColumn.LAST_NAME]: 'Nom',
    [MemberExportColumn.FIRST_NAME]: 'Prénom',
    [MemberExportColumn.GENDER]: 'Genre',
    [MemberExportColumn.BIRTH_DATE]: 'Date de naissance',
    [MemberExportColumn.AGE]: 'Âge',
    [MemberExportColumn.NATIONALITY]: 'Nationalité',
    [MemberExportColumn.STATUS]: 'Statut du membre',
    [MemberExportColumn.EMAIL]: 'Email',
    [MemberExportColumn.PHONE_NUMBER]: 'Téléphone',
    [MemberExportColumn.ADDRESS_STREET]: 'Adresse',
    [MemberExportColumn.ADDRESS_ZIP]: 'Code postal',
    [MemberExportColumn.ADDRESS_CITY]: 'Ville',
    [MemberExportColumn.TEAMS]: 'Équipes',
    [MemberExportColumn.LICENSE_NUMBER]: 'N° de licence',
    [MemberExportColumn.LICENSE_STATUS]: 'Statut de la licence',
    [MemberExportColumn.LICENSE_PAID]: 'Licence payée',
    [MemberExportColumn.LICENSE_AMOUNT]: 'Montant (€)',
    [MemberExportColumn.LICENSE_APPROVED_AT]: 'Validée le',
    [MemberExportColumn.HEALTH_DECLARATION]: 'Attestation santé',
    [MemberExportColumn.LEGAL_REP_FIRST_NAME]: 'Représentant légal - prénom',
    [MemberExportColumn.LEGAL_REP_LAST_NAME]: 'Représentant légal - nom',
    [MemberExportColumn.LEGAL_REP_EMAIL]: 'Représentant légal - email',
    [MemberExportColumn.LEGAL_REP_PHONE]: 'Représentant légal - téléphone',
    [MemberExportColumn.CREATED_AT]: 'Créé le',
};

/** Regroupement d'affichage du sélecteur de colonnes. */
export const MemberExportColumnGroups: { label: string; columns: MemberExportColumn[] }[] = [
    {
        label: 'Identité',
        columns: [
            MemberExportColumn.LAST_NAME,
            MemberExportColumn.FIRST_NAME,
            MemberExportColumn.GENDER,
            MemberExportColumn.BIRTH_DATE,
            MemberExportColumn.AGE,
            MemberExportColumn.NATIONALITY,
            MemberExportColumn.STATUS,
        ],
    },
    {
        label: 'Contact',
        columns: [
            MemberExportColumn.EMAIL,
            MemberExportColumn.PHONE_NUMBER,
            MemberExportColumn.ADDRESS_STREET,
            MemberExportColumn.ADDRESS_ZIP,
            MemberExportColumn.ADDRESS_CITY,
        ],
    },
    {
        label: 'Licence & équipes',
        columns: [
            MemberExportColumn.TEAMS,
            MemberExportColumn.LICENSE_NUMBER,
            MemberExportColumn.LICENSE_STATUS,
            MemberExportColumn.LICENSE_PAID,
            MemberExportColumn.LICENSE_AMOUNT,
            MemberExportColumn.LICENSE_APPROVED_AT,
            MemberExportColumn.HEALTH_DECLARATION,
        ],
    },
    {
        label: 'Représentant légal',
        columns: [
            MemberExportColumn.LEGAL_REP_FIRST_NAME,
            MemberExportColumn.LEGAL_REP_LAST_NAME,
            MemberExportColumn.LEGAL_REP_EMAIL,
            MemberExportColumn.LEGAL_REP_PHONE,
        ],
    },
    {
        label: 'Suivi',
        columns: [MemberExportColumn.CREATED_AT],
    },
];

/** Présélection : ce qu'on veut dans 90 % des exports (une liste d'appel). */
export const MemberExportDefaultColumns: MemberExportColumn[] = [
    MemberExportColumn.LAST_NAME,
    MemberExportColumn.FIRST_NAME,
    MemberExportColumn.BIRTH_DATE,
    MemberExportColumn.EMAIL,
    MemberExportColumn.PHONE_NUMBER,
    MemberExportColumn.TEAMS,
    MemberExportColumn.LICENSE_NUMBER,
    MemberExportColumn.LICENSE_STATUS,
    MemberExportColumn.LICENSE_PAID,
];
