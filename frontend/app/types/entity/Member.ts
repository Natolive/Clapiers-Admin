import type { Team } from './Team';
import type { MemberGender } from '~/types/enum/MemberGender';

export type Member = {
    id: number;
    firstName: string;
    lastName: string;
    color: string;
    phoneNumber: string;
    email: string;
    licensePaid: boolean;
    /** Présent dans la liste paginée / mon-équipe : licence déposée dans la médiathèque (saison courante). */
    hasLicenseDocument?: boolean;
    /** Présent dans « mon équipe » : photo de profil déposée dans la médiathèque. */
    hasProfilePicture?: boolean;
    licenseNumber: string | null;
    address: {
        street: string;
        zip: string;
        city: string;
    };
    gender: MemberGender;
    birthDate: string;
    nationality: string;
    /** Représentant légal (mineur) — champs vides si majeur. */
    legalRepresentative?: {
        firstName: string;
        lastName: string;
        email: string;
        phone: string;
    };
    /** Optionnel : données persistées avant la migration multi-équipes */
    teams?: Team[];
    createdAt: string;
    updatedAt: string;
};
