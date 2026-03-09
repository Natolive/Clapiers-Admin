import type { Game, CreateUpdateGameDto } from '~/types/entity/Game';

export type GetGamesParams = {
    teamId?: number | null;
    start?: string;
    end?: string;
};

export class GameRepository {
    private api = useApi();

    async getAll(params: GetGamesParams = {}): Promise<Game[]> {
        return await this.api<Game[]>('/game', {
            method: 'GET',
            params: {
                ...(params.teamId ? { teamId: params.teamId } : {}),
                ...(params.start  ? { start:  params.start  } : {}),
                ...(params.end    ? { end:    params.end    } : {}),
            },
        });
    }

    async create(dto: CreateUpdateGameDto): Promise<Game> {
        return await this.api<Game>('/game', {
            method: 'POST',
            body: dto,
        });
    }

    async update(id: number, dto: CreateUpdateGameDto): Promise<Game> {
        return await this.api<Game>(`/game/${id}`, {
            method: 'PUT',
            body: dto,
        });
    }

    async delete(id: number): Promise<void> {
        await this.api<void>(`/game/${id}`, { method: 'DELETE' });
    }
}
