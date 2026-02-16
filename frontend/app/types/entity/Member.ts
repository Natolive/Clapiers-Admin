import type { Team } from './Team';

export type Member = {
    id: number;
    firstName: string;
    lastName: string;
    color: string;
    phoneNumber: string;
    email: string;
    licensePaid: boolean;
    licenseFileName: string | null;
    team: Team;
    createdAt: string;
    updatedAt: string;
};
