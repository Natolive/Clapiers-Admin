export enum AppUserRole {
    SUPER_ADMIN = "ROLE_SUPER_ADMIN",
    COACH = "ROLE_COACH",
    USER = "ROLE_USER",
    VIEW_MESSAGE = "ROLE_VIEW_MESSAGE"
}

/** invited = lien envoyé non utilisé, expired = lien périmé, active = mot de passe choisi. */
export type InvitationStatus = 'invited' | 'expired' | 'active';

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
    invitationStatus?: InvitationStatus;
    createdAt: string;
    updatedAt: string;
};