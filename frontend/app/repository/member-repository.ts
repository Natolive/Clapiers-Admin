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
    hasLicense?: boolean;
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
                ...(params.hasLicense !== undefined ? { hasLicense: params.hasLicense } : {}),
            }
        });
    }

    async createUpdate(firstName: string, lastName: string, phoneNumber: string, email: string, teamId: number, id: number|null = null): Promise<Member> {
        return await this.api<Member>('/member', {
            method: 'POST',
            body: { id, firstName, lastName, phoneNumber, email, teamId }
        });
    }

    async toggleLicense(id: number): Promise<Member> {
        return await this.api<Member>(`/member/${id}/toggle-license`, {
            method: 'PATCH'
        });
    }

    async uploadLicense(id: number, file: File): Promise<Member> {
        const formData = new FormData();
        formData.append('file', file);
        return await this.api<Member>(`/member/${id}/upload-license`, {
            method: 'POST',
            body: formData
        });
    }

    async deleteLicense(id: number): Promise<Member> {
        return await this.api<Member>(`/member/${id}/delete-license`, {
            method: 'DELETE'
        });
    }

    async uploadProfilePicture(id: number, file: File): Promise<Member> {
        const formData = new FormData();
        formData.append('file', file);
        return await this.api<Member>(`/member/${id}/upload-profile-picture`, {
            method: 'POST',
            body: formData
        });
    }

    async deleteProfilePicture(id: number): Promise<Member> {
        return await this.api<Member>(`/member/${id}/delete-profile-picture`, {
            method: 'DELETE'
        });
    }

    async getByTeam(teamId: number): Promise<Member[]> {
        return await this.api<Member[]>(`/member/team/${teamId}`, {
            method: 'GET'
        });
    }

}
