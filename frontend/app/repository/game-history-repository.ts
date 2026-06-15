import type { GameHistory } from '~/types/entity/GameHistory';
import type { PaginatedResult } from '~/types/custom/Pagination';

export interface GameHistoryPaginationParams {
    page: number;
    limit: number;
    gameId?: number | null;
    teamId?: number | null;
}

export class GameHistoryRepository {
    private api = useApi();

    async getPaginated(params: GameHistoryPaginationParams): Promise<PaginatedResult<GameHistory>> {
        return await this.api<PaginatedResult<GameHistory>>('/game/history', {
            method: 'GET',
            params: {
                page: params.page,
                limit: params.limit,
                ...(params.gameId ? { gameId: params.gameId } : {}),
                ...(params.teamId ? { teamId: params.teamId } : {}),
            },
        });
    }
}
