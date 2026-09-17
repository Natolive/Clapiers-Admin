import type { Member } from "~/types/entity/Member";
import type { MemberExportColumn } from "~/types/enum/MemberExportColumn";
import type { MemberExportFile } from "~/types/enum/MemberExportFile";

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
    season?: string;
    /** true = inscrits FSGT, false = non inscrits, undefined = les deux. */
    fsgtRegistered?: boolean;
}

export interface FsgtRegistration {
    memberId: number;
    season: string;
    fsgtRegistered: boolean;
    fsgtRegisteredAt: string | null;
    licenseNumber: string | null;
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
                ...(params.season ? { season: params.season } : {}),
                ...(params.fsgtRegistered !== undefined ? { fsgtRegistered: params.fsgtRegistered } : {}),
            }
        });
    }

    /**
     * Déclare (ou retire) l'inscription FSGT du licencié pour la saison. Le
     * numéro est exigé côté serveur pour cocher : c'est la fédération qui
     * l'attribue, une inscription sans numéro ne se vérifie pas.
     */
    async setFsgtRegistration(
        id: number,
        registered: boolean,
        licenseNumber?: string | null,
        season?: string,
    ): Promise<FsgtRegistration> {
        return await this.api<FsgtRegistration>(`/member/${id}/fsgt`, {
            method: 'PUT',
            body: {
                registered,
                ...(licenseNumber ? { licenseNumber } : {}),
                ...(season ? { season } : {}),
            },
        });
    }

    async createUpdate(body: Record<string, any>): Promise<Member> {
return await this.api<Member>('/member', {
            method: 'POST',
            body,
        });
    }

    /**
     * Suppression douce : la fiche est datée côté serveur et disparaît de
     * toutes les listes, mais licences, paiements et médiathèque restent en
     * base. Aucune route de restauration pour l'instant (voir le back).
     */
    async delete(id: number): Promise<{ id: number; deleted: boolean }> {
        return await this.api<{ id: number; deleted: boolean }>(`/member/${id}`, {
            method: 'DELETE'
        });
    }

    /**
     * Export de la liste : mêmes filtres que `getPaginated` (on exporte ce qu'on
     * voit), plus les colonnes et les pièces choisies. Sans pièce c'est un
     * xlsx, avec c'est un zip — d'où l'extension calculée ici aussi. La route
     * est authentifiée par en-tête Bearer, donc pas de simple `href` : on passe
     * par un blob.
     */
    async export(
        params: Omit<PaginationParams, 'page' | 'limit' | 'sortField' | 'sortOrder'>,
        columns: MemberExportColumn[],
        files: MemberExportFile[] = [],
        memberIds: number[] = [],
    ): Promise<void> {
        const query = new URLSearchParams();
        if (params.search) query.set('search', params.search);
        if (params.teamId) query.set('teamId', String(params.teamId));
        if (params.licensePaid !== undefined) query.set('licensePaid', String(params.licensePaid));
        if (params.season) query.set('season', params.season);
        if (params.fsgtRegistered !== undefined) query.set('fsgtRegistered', String(params.fsgtRegistered));
        columns.forEach(column => query.append('columns[]', column));
        files.forEach(file => query.append('files[]', file));
        // Aucune ligne cochée = toute la population filtrée (pas de paramètre).
        memberIds.forEach(id => query.append('memberIds[]', String(id)));

        const extension = files.length ? 'zip' : 'xlsx';
        const fileName = `licencies-${params.season ?? 'saison-courante'}.${extension}`;
        await useAuthenticatedFile().download(`/member/export?${query.toString()}`, fileName);
    }

    async getByTeam(teamId: number, season?: string): Promise<Member[]> {
        return await this.api<Member[]>(`/member/team/${teamId}`, {
            method: 'GET',
            params: season ? { season } : {},
        });
    }

}
