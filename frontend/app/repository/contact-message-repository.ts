import type { ContactMessage } from "~/types/entity/ContactMessage";
import type { PaginatedResult } from "~/types/custom/Pagination";

export interface ContactMessagePaginationParams {
    page: number;
    limit: number;
    search?: string;
}

export class ContactMessageRepository {
    private api = useApi()

    async create(firstName: string, lastName: string, email: string, subject: string, message: string): Promise<ContactMessage> {
        return await this.api<ContactMessage>('/contact-message', {
            method: 'POST',
            body: { firstName, lastName, email, subject, message }
        });
    }

    async getPaginated(params: ContactMessagePaginationParams): Promise<PaginatedResult<ContactMessage>> {
        return await this.api<PaginatedResult<ContactMessage>>('/contact-message', {
            method: 'GET',
            params: {
                page: params.page,
                limit: params.limit,
                ...(params.search ? { search: params.search } : {}),
            }
        });
    }
}
