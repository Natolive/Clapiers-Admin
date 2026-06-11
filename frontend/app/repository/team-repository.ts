import type {Team} from "~/types/entity/Team";
import type {Member} from "~/types/entity/Member";

export type MyTeamGroup = {
    team: Team;
    members: Member[];
};

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

    /** Licenciés des équipes gérées par l'utilisateur, groupés par équipe */
    async getMyTeams(): Promise<MyTeamGroup[]> {
        return await this.api<MyTeamGroup[]>('/team/my-team', {
            method: 'GET'
        });
    }
}
