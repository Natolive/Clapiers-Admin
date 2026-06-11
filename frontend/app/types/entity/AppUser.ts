export enum AppUserRole {
    SUPER_ADMIN = "ROLE_SUPER_ADMIN",
    ADMIN = "ROLE_ADMIN",
    USER = "ROLE_USER",
    VIEW_MESSAGE = "ROLE_VIEW_MESSAGE"
}

import type { Member } from './Member';
import type { Team } from './Team';

export type AppUser = {
    id: number;
    email: string;
    roles: AppUserRole[];
    member: Member | null;
    /** Équipes gérées par l'utilisateur (coachs) */
    teams: Team[];
    createdAt: string;
    updatedAt: string;
};