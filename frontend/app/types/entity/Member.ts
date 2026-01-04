import type { Team } from './Team';

export type Member = {
    id: number;
    firstName: string;
    lastName: string;
    color: string;
    phoneNumber: string;
    email: string;
    team: Team;
    createdAt: string;
    updatedAt: string;
};
