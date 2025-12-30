export enum AppUserRole {
    SUPER_ADMIN = "ROLE_SUPER_ADMIN",
    ADMIN = "ROLE_ADMIN",
    USER = "ROLE_USER"
}

export type AppUser = {
    id: number;
    email: string;
    roles: AppUserRole[];
    createdAt: string;
    updatedAt: string;
};