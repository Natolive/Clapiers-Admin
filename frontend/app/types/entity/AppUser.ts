export enum AppUserRole {
    SUPER_ADMIN = "ROLE_SUPER_ADMIN",
    ADMIN = "ROLE_ADMIN",
    USER = "ROLE_USER",
    VIEW_MESSAGE = "ROLE_VIEW_MESSAGE",
    CONFIRM_MESSAGE = "ROLE_CONFIRM_MESSAGE"
}

import type { Member } from './Member';

export type AppUser = {
    id: number;
    email: string;
    roles: AppUserRole[];
    member: Member | null;
    createdAt: string;
    updatedAt: string;
};