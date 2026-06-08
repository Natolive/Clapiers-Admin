import type { SalleClosure, CreateSalleClosureDto } from '~/types/entity/SalleClosure';

export class SalleClosureRepository {
    private api = useApi();

    async getAll(): Promise<SalleClosure[]> {
        return await this.api<SalleClosure[]>('/salle-closure', {
            method: 'GET',
        });
    }

    async create(dto: CreateSalleClosureDto): Promise<SalleClosure> {
        return await this.api<SalleClosure>('/salle-closure', {
            method: 'POST',
            body: dto,
        });
    }

    async delete(id: number): Promise<void> {
        await this.api<void>(`/salle-closure/${id}`, { method: 'DELETE' });
    }
}
