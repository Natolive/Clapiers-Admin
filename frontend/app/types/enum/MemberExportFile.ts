/**
 * Pièces de la médiathèque joignables à l'export. Miroir de
 * `MemberMediaDefaults::documentSlots()` côté PHP — un nouveau slot par défaut
 * s'ajoute là-bas, puis ici pour apparaître dans le sélecteur.
 *
 * `seasonScoped` n'est qu'une indication d'affichage : le back cherche toujours
 * les pièces de saison dans le dossier de la saison exportée.
 */
export enum MemberExportFile {
    IDENTITY_PHOTO = 'identity_photo',
    ID_CARD = 'id_card',
    LICENSE = 'license',
    MEDICAL_CERTIFICATE = 'medical_certificate',
    ATTESTATION = 'attestation',
}

export const MemberExportFileOptions: { value: MemberExportFile; label: string; seasonScoped: boolean }[] = [
    { value: MemberExportFile.IDENTITY_PHOTO, label: "Photo d'identité", seasonScoped: false },
    { value: MemberExportFile.ID_CARD, label: "Pièce d'identité", seasonScoped: false },
    { value: MemberExportFile.LICENSE, label: 'Licence', seasonScoped: true },
    { value: MemberExportFile.MEDICAL_CERTIFICATE, label: 'Certificat médical', seasonScoped: true },
    { value: MemberExportFile.ATTESTATION, label: "Attestation sur l'honneur", seasonScoped: true },
];
