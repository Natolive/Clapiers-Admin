import type { Season } from './Season';

export type Team = {
    id: number;
    name: string;
    season: Season;
    createdAt: string;
    updatedAt: string;
};
