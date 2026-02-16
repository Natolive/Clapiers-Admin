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

    async toggleLicense(id: number): Promise<Member> {
        return await this.api<Member>(`/member/${id}/toggle-license`, {
            method: 'PATCH'
        });
    }

    async uploadLicense(id: number, file: File): Promise<Member> {
        const formData = new FormData();
        formData.append('file', file);
        return await this.api<Member>(`/member/${id}/upload-license`, {
            method: 'POST',
            body: formData
        });
    }

    async deleteLicense(id: number): Promise<Member> {
        return await this.api<Member>(`/member/${id}/delete-license`, {
            method: 'DELETE'
        });
    }

    async getByTeam(teamId: number): Promise<Member[]> {
        return await this.api<Member[]>(`/member/team/${teamId}`, {
            method: 'GET'
        });
    }

}
