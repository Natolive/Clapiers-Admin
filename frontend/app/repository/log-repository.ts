import type { LogEntry } from '~/types/entity/Log';
import type { PaginatedResult } from '~/types/custom/Pagination';

export interface LogPaginationParams {
    page: number;
    limit: number;
    level?: string;
    search?: string;
}

/**
 * Endpoints de consultation des logs applicatifs (SUPER_ADMIN, API authentifiée).
 */
export class LogRepository {
    private api = useApi();

    async getPaginated(params: LogPaginationParams): Promise<PaginatedResult<LogEntry>> {
        return await this.api<PaginatedResult<LogEntry>>('/logs/paginated', {
            method: 'GET',
            params: {
                page: params.page,
                limit: params.limit,
                ...(params.level ? { level: params.level } : {}),
                ...(params.search ? { search: params.search } : {}),
            },
        });
    }

    async clear(): Promise<{ deleted: number }> {
        return await this.api<{ deleted: number }>('/logs', { method: 'DELETE' });
    }
}
