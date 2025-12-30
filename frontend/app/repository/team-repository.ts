import type {Team} from "~/types/entity/Team";

export class TeamRepository {
    private api = useApi()

    async getAll(): Promise<Team[]> {
        return await this.api<Team[]>('/team', {
            method: 'GET'
        });
    }

    async createUpdate(name: string, id: number|null = null): Promise<Team> {
        return await this.api<Team>('/team', {
            method: 'POST',
            body: { id, name }
        });
    }

    async countBySeason(seasonId: number): Promise<number> {
        return await this.api<number>('/team/count-by-season', {
            method: 'POST',
            body: { seasonId }
        });
    }
}
