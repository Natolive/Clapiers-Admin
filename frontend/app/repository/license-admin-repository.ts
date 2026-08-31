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
    season?: string;
}

export interface LicenseReviewDocument {
    key: string;
    label: string;
    uploaded: boolean;
    nodeId: string | null;
    originalName: string | null;
    mimeType: string | null;
    size: number | null;
}

export interface LicenseReviewExistingMember {
    id: number;
    firstName: string;
    lastName: string;
    email: string;
    status: string;
    hasLicenseThisSeason: boolean;
}

export interface LicenseReview {
    license: License;
    memberId: number;
    documents: LicenseReviewDocument[];
    existingMember: LicenseReviewExistingMember | null;
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
                ...(params.season ? { season: params.season } : {}),
            },
        });
    }

    /** Dossier complet d'une demande : infos + état des pièces déposées. */
    async getReview(id: number): Promise<LicenseReview> {
        return await this.api<LicenseReview>(`/license/${id}`, { method: 'GET' });
    }

    async getTiers(): Promise<LicenseTier[]> {
        const response = await this.api<{ data?: LicenseTier[] }>('/license/tiers', { method: 'GET' });
        return response.data ?? [];
    }

    async approve(id: number, helloAssoTierId: number, amount: number, replaceMemberId?: number | null): Promise<License> {
        return await this.api<License>(`/license/${id}/approve`, {
            method: 'POST',
            body: { helloAssoTierId, amount, ...(replaceMemberId ? { replaceMemberId } : {}) },
        });
    }

    /** Nouveau magic link (30 j) + e-mail : recours quand le lien a expiré. */
    async resendPaymentLink(id: number): Promise<License> {
        return await this.api<License>(`/license/${id}/resend-link`, { method: 'POST' });
    }

    async reject(id: number, reason: string): Promise<License> {
        return await this.api<License>(`/license/${id}/reject`, {
            method: 'POST',
            body: { reason },
        });
    }
}
