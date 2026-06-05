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
}
