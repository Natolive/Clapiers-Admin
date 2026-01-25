import type { ContactMessage } from "~/types/entity/ContactMessage";

export class ContactMessageRepository {
    private api = useApi()

    async create(firstName: string, lastName: string, email: string, subject: string, message: string): Promise<ContactMessage> {
        return await this.api<ContactMessage>('/contact-message', {
            method: 'POST',
            body: { firstName, lastName, email, subject, message }
        });
    }

    async getAll(): Promise<ContactMessage[]> {
        return await this.api<ContactMessage[]>('/contact-message', {
            method: 'GET'
        });
    }

    async getUnread(): Promise<ContactMessage[]> {
        return await this.api<ContactMessage[]>('/contact-message/unread', {
            method: 'GET'
        });
    }

    async getRead(): Promise<ContactMessage[]> {
        return await this.api<ContactMessage[]>('/contact-message/read', {
            method: 'GET'
        });
    }

    async countUnread(): Promise<{ count: number }> {
        return await this.api<{ count: number }>('/contact-message/unread/count', {
            method: 'GET'
        });
    }

    async markAsRead(id: number): Promise<ContactMessage> {
        return await this.api<ContactMessage>(`/contact-message/${id}/read`, {
            method: 'PUT'
        });
    }
}
