import type { Member } from "~/types/entity/Member";

export class MemberRepository {
    private api = useApi()

    async getAll(): Promise<Member[]> {
        return await this.api<Member[]>('/member', {
            method: 'GET'
        });
    }

    async createUpdate(firstName: string, lastName: string, phoneNumber: string, email: string, teamId: number, id: number|null = null): Promise<Member> {
        return await this.api<Member>('/member', {
            method: 'POST',
            body: { id, firstName, lastName, phoneNumber, email, teamId }
        });
    }

    async getByTeam(teamId: number): Promise<Member[]> {
        return await this.api<Member[]>(`/member/team/${teamId}`, {
            method: 'GET'
        });
    }
}
