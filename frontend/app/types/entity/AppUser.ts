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
    /**
     * Équipes gérées par l'utilisateur (coachs). Optionnel : un store
     * d'authentification persisté avant la migration multi-équipes peut
     * encore contenir des utilisateurs sans ce tableau.
     */
    teams?: Team[];
    createdAt: string;
    updatedAt: string;
};