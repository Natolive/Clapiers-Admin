import type { Credentials } from "~/types/custom/Credentials";

export class AuthenticationRepository {
    private api = useApi()

    async login(payload: Credentials): Promise<{ token: string }> {
        // Now this returns the actual data directly
        return await this.api<{ token: string }>('/login', {
            method: 'POST',
            body: payload
        });
    }

    async me() {
        return await this.api<any>('/user/me', {
            method: 'GET'
        });
    }
}