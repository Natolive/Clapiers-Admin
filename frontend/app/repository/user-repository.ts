import type { AppUser, AppUserRole } from "~/types/entity/AppUser";
import type { PaginatedResult, PaginationParams } from "~/repository/member-repository";

/** Vue publique d'une invitation chef d'équipe (lien par e-mail). */
export interface InvitationView {
    email: string;
    firstName: string | null;
}

export class UserRepository {
    private api = useApi()
    private publicApi = usePublicApi()

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

    /** Crée ou reprend le compte chef d'équipe du licencié et lui envoie le lien. */
    async inviteCoach(memberId: number, teamIds: number[]): Promise<AppUser> {
        return await this.api<AppUser>('/user/invite', {
            method: 'POST',
            body: { memberId, teamIds }
        });
    }

    async getInvitation(token: string): Promise<InvitationView> {
        return await this.publicApi<InvitationView>(`/public/invitation/${token}`);
    }

    async acceptInvitation(token: string, password: string): Promise<{ email: string }> {
        return await this.publicApi<{ email: string }>(`/public/invitation/${token}`, {
            method: 'POST',
            body: { password }
        });
    }
}
