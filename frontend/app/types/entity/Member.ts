import type { Team } from './Team';

export type Member = {
    id: number;
    firstName: string;
    lastName: string;
    team: Team;
    createdAt: string;
    updatedAt: string;
};
