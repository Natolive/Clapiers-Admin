import type {Season} from "~/types/entity/Season";

export class SeasonRepository {
    private api = useApi()

    async getAll(): Promise<Season[]> {
        return await this.api<Season[]>('/season', {
            method: 'GET'
        });
    }

    async getActual(): Promise<Season> {
        return await this.api<Season>('/season/actual', {
            method: 'GET'
        });
    }
}
