import type { Team } from '~/types/entity/Team';
import type {GameVenue} from "~/types/enum/GameVenue";

export type Game = {
    id: number;
    opponent: string;
    date: string; // 'YYYY-MM-DD'
    meetingTime: string | null; // e.g. '14h30', informative only
    venue: GameVenue; // 'home' | 'away'
    location: string | null;
    team: Team;
    createdAt: string;
    updatedAt: string;
};

export type CreateUpdateGameDto = {
    opponent: string;
    date: string; // 'YYYY-MM-DD'
    meetingTime?: string | null;
    venue: string;
    location?: string | null;
    teamId?: number | null;
    id?: number | null;
};
