import type { License } from '~/types/entity/License';
import type { PaginatedResult } from '~/repository/member-repository';

export interface LicenseTier {
    id: number;
    label: string;
    amount: number;
}

export interface LicensePaginationParams {
    page: number;
    limit: number;
    status?: string;
    search?: string;
}

/**
 * Endpoints back-office des licences (SUPER_ADMIN, API authentifiée).
 * Distinct de LicenseRepository qui gère le parcours public (usePublicApi).
 */
export class LicenseAdminRepository {
    private api = useApi();

    async getPaginated(params: LicensePaginationParams): Promise<PaginatedResult<License>> {
        return await this.api<PaginatedResult<License>>('/license/paginated', {
            method: 'GET',
            params: {
                page: params.page,
                limit: params.limit,
                ...(params.status ? { status: params.status } : {}),
                ...(params.search ? { search: params.search } : {}),
            },
        });
    }

    async getTiers(): Promise<LicenseTier[]> {
        const response = await this.api<{ data?: LicenseTier[] }>('/license/tiers', { method: 'GET' });
        return response.data ?? [];
    }

    async approve(id: number, helloAssoTierId: number, amount: number): Promise<License> {
        return await this.api<License>(`/license/${id}/approve`, {
            method: 'POST',
            body: { helloAssoTierId, amount },
        });
    }

    async reject(id: number, reason: string): Promise<License> {
        return await this.api<License>(`/license/${id}/reject`, {
            method: 'POST',
            body: { reason },
        });
    }
}
