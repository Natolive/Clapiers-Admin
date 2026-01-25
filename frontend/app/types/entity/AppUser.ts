export enum AppUserRole {
    SUPER_ADMIN = "ROLE_SUPER_ADMIN",
    ADMIN = "ROLE_ADMIN",
    USER = "ROLE_USER",
    VIEW_MESSAGE = "ROLE_VIEW_MESSAGE",
    CONFIRM_MESSAGE = "ROLE_CONFIRM_MESSAGE"
}

export type AppUser = {
    id: number;
    email: string;
    roles: AppUserRole[];
    createdAt: string;
    updatedAt: string;
};