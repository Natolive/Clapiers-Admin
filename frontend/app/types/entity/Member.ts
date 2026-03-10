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
    licenseFileName: string | null;
    profilePicture: string | null;
    licenseNumber: string | null;
    address: {
        street: string;
        zip: string;
        city: string;
    };
    gender: MemberGender;
    birthDate: string;
    nationality: string;
    team: Team;
    createdAt: string;
    updatedAt: string;
};
