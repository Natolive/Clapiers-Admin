export interface SeasonSettings {
    /** Saison courante retenue (réglage, sinon calcul par date). */
    season: string;
    /** Saison suggérée, calculée depuis la date du jour. */
    suggestion: string;
}

export class SettingRepository {
    private api = useApi();

    async getSeason(): Promise<SeasonSettings> {
        return await this.api<SeasonSettings>('/settings/season', { method: 'GET' });
    }

    async setSeason(season: string): Promise<{ season: string }> {
        return await this.api<{ season: string }>('/settings/season', {
            method: 'PUT',
            body: { season },
        });
    }
}
