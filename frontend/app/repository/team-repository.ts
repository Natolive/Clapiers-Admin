import type {Team} from "~/types/entity/Team";
import type {Member} from "~/types/entity/Member";

export type MyTeamGroup = {
    team: Team;
    members: Member[];
};

export class TeamRepository {
    private api = useApi()

    async getAll(season?: string): Promise<Team[]> {
        return await this.api<Team[]>('/team', {
            method: 'GET',
            ...(season ? { params: { season } } : {}),
        });
    }

    /** Suppression douce : l'équipe disparaît des listes, ses matchs restent. */
    async delete(id: number): Promise<{ id: number; deleted: boolean }> {
        return await this.api<{ id: number; deleted: boolean }>(`/team/${id}`, {
            method: 'DELETE'
        });
    }

    /**
     * Ajoute/retire des licenciés à l'équipe et renvoie l'équipe à jour avec son
     * effectif de la saison. Ajouts et retraits explicites : le rattachement
     * n'est pas saisonnier, envoyer la liste affichée détacherait les licenciés
     * des autres saisons.
     */
    async updateRoster(
        id: number,
        changes: { add?: number[]; remove?: number[]; season?: string }
    ): Promise<Team & { members: Member[] }> {
        return await this.api<Team & { members: Member[] }>(`/team/${id}/members`, {
            method: 'PATCH',
            body: {
                add: changes.add ?? [],
                remove: changes.remove ?? [],
                ...(changes.season ? { season: changes.season } : {}),
            }
        });
    }

    async createUpdate(name: string, id: number|null = null, userIds?: number[]): Promise<Team> {
        return await this.api<Team>('/team', {
            method: 'POST',
            body: { id, name, ...(userIds !== undefined ? { userIds } : {}) }
        });
    }

    /** Licenciés des équipes gérées par l'utilisateur, groupés par équipe */
    async getMyTeams(): Promise<MyTeamGroup[]> {
        return await this.api<MyTeamGroup[]>('/team/my-team', {
            method: 'GET'
        });
    }
}
