import type { AppUser, AppUserRole } from "~/types/entity/AppUser";
import type { PaginatedResult, PaginationParams } from "~/repository/member-repository";

export class UserRepository {
    private api = useApi()

    async getAll(): Promise<AppUser[]> {
        return await this.api<AppUser[]>('/user', {
            method: 'GET'
        });
    }

    async getPaginated(params: PaginationParams): Promise<PaginatedResult<AppUser>> {
        return await this.api<PaginatedResult<AppUser>>('/user/paginated', {
            method: 'GET',
            params: {
                page: params.page,
                limit: params.limit,
                sortField: params.sortField,
                sortOrder: params.sortOrder,
                ...(params.search ? { search: params.search } : {}),
            }
        });
    }

    async createUpdate(
        email: string,
        role: AppUserRole,
        password: string | null = null,
        id: number | null = null,
    ): Promise<AppUser> {
        return await this.api<AppUser>('/user', {
            method: 'POST',
            body: { id, email, role, password }
        });
    }

    async linkMember(userId: number, memberId: number): Promise<AppUser> {
        return await this.api<AppUser>(`/user/${userId}/link-member`, {
            method: 'PATCH',
            body: { memberId }
        });
    }

    async unlinkMember(userId: number): Promise<AppUser> {
        return await this.api<AppUser>(`/user/${userId}/unlink-member`, {
            method: 'PATCH'
        });
    }
}
