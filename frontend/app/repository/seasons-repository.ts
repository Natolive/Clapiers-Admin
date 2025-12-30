import type {Season} from "~/types/entity/Season";

export class SeasonsRepository {
    private api = useApi()

    async getAll(): Promise<Season[]> {
        return await this.api<Season[]>('/seasons', {
            method: 'GET'
        });
    }

    async getActual(): Promise<Season> {
        return await this.api<Season>('/seasons/actual', {
            method: 'GET'
        });
    }
}
