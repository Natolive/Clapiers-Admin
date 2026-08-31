import type { InscriptionsStatus } from '~/composables/useInscriptionsStatus';

export interface SeasonSettings {
    /** Saison courante retenue (réglage, sinon calcul par date). */
    season: string;
    /** Saison suggérée, calculée depuis la date du jour. */
    suggestion: string;
    /** Saisons enregistrées, la plus récente d'abord (courante incluse). */
    seasons: string[];
}

/** Configuration HelloAsso : le secret n'est jamais renvoyé par l'API. */
export interface HelloAssoConfig {
    baseUrl: string;
    clientId: string;
    organizationSlug: string;
    membershipFormType: string;
    membershipFormSlug: string;
    clientSecretDefined: boolean;
}

/** Configuration Bunny Storage : la clé d'accès n'est jamais renvoyée par l'API. */
export interface BunnyConfig {
    storageUrl: string;
    storageKeyDefined: boolean;
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

    async getHelloAssoConfig(): Promise<HelloAssoConfig> {
        return await this.api<HelloAssoConfig>('/settings/helloasso', { method: 'GET' });
    }

    /** Champs vides = inchangés (notamment le secret client). */
    async setHelloAssoConfig(body: Partial<Omit<HelloAssoConfig, 'clientSecretDefined'>> & { clientSecret?: string }): Promise<HelloAssoConfig> {
        return await this.api<HelloAssoConfig>('/settings/helloasso', {
            method: 'PUT',
            body,
        });
    }

    async getBunnyConfig(): Promise<BunnyConfig> {
        return await this.api<BunnyConfig>('/settings/bunny', { method: 'GET' });
    }

    /** Champs vides = inchangés (notamment la clé d'accès). */
    async setBunnyConfig(body: { storageUrl?: string; storageKey?: string }): Promise<BunnyConfig> {
        return await this.api<BunnyConfig>('/settings/bunny', {
            method: 'PUT',
            body,
        });
    }

    async getInscriptionsStatus(): Promise<InscriptionsStatus> {
        return await this.api<InscriptionsStatus>('/settings/inscriptions', { method: 'GET' });
    }

    /** Réglages omis = inchangés (les deux drapeaux sont indépendants). */
    async setInscriptionsStatus(body: Partial<InscriptionsStatus>): Promise<InscriptionsStatus> {
        return await this.api<InscriptionsStatus>('/settings/inscriptions', {
            method: 'PUT',
            body,
        });
    }
}
