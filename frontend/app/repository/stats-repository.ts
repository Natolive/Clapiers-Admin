export type DashboardStats = {
    members: {
        total: number;
        withLicense: number;
        withoutLicense: number;
        byGender: { male: number; female: number; other: number };
        age: {
            average: number;
            min: number;
            max: number;
            byRange: Record<string, number>;
        };
        createdAt: {
            newThisMonth: number;
            newThisYear: number;
            byMonth: { month: string; total: number }[];
        };
    };
    games: {
        total: number;
        thisSeason: number;
        upcoming: number;
    };
    teams: {
        total: number;
    };
    licenses: {
        total: number;
        byStatus: Record<string, number>;
    };
    users: {
        total: number;
    };
    messages: {
        total: number;
    };
};

export class StatsRepository {
    private api = useApi();

    async getDashboard(season?: string): Promise<DashboardStats> {
        return await this.api<DashboardStats>('/stats/dashboard', {
            params: season ? { season } : {},
        });
    }
}
