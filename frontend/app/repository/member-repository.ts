import type { Member } from "~/types/entity/Member";

export interface PaginatedResult<T> {
    data: T[];
    total: number;
}

export interface PaginationParams {
    page: number;
    limit: number;
    sortField: string;
    sortOrder: string;
    search?: string;
    teamId?: number;
    licensePaid?: boolean;
}

export class MemberRepository {
    private api = useApi()

    async getAll(): Promise<Member[]> {
        return await this.api<Member[]>('/member', {
            method: 'GET'
        });
    }

    async getPaginated(params: PaginationParams): Promise<PaginatedResult<Member>> {
        return await this.api<PaginatedResult<Member>>('/member/paginated', {
            method: 'GET',
            params: {
                page: params.page,
                limit: params.limit,
                sortField: params.sortField,
                sortOrder: params.sortOrder,
                ...(params.search ? { search: params.search } : {}),
                ...(params.teamId ? { teamId: params.teamId } : {}),
                ...(params.licensePaid !== undefined ? { licensePaid: params.licensePaid } : {}),
            }
        });
    }

    async createUpdate(body: Record<string, any>): Promise<Member> {
return await this.api<Member>('/member', {
            method: 'POST',
            body,
        });
    }

    async getByTeam(teamId: number): Promise<Member[]> {
        return await this.api<Member[]>(`/member/team/${teamId}`, {
            method: 'GET'
        });
    }

}
