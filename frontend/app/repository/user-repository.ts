import type { AppUser, AppUserRole } from "~/types/entity/AppUser";

export class UserRepository {
    private api = useApi()

    async getAll(): Promise<AppUser[]> {
        return await this.api<AppUser[]>('/user', {
            method: 'GET'
        });
    }

    async createUpdate(
        email: string,
        role: AppUserRole,
        password: string | null = null,
        id: number | null = null,
        teamId: number | null = null
    ): Promise<AppUser> {
        return await this.api<AppUser>('/user', {
            method: 'POST',
            body: { id, email, role, password, teamId }
        });
    }
}
