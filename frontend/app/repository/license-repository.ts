import type { License } from '~/types/entity/License';

/** Vue publique du portail de paiement (magic link). */
export interface LicensePaymentView {
    status: string;
    season: string;
    amount: number | null;
    firstName: string;
    lastName: string;
}

export interface SubmitLicenseRequestBody {
    firstName: string;
    lastName: string;
    phoneNumber: string;
    email: string;
    addressStreet: string;
    addressZip: string;
    addressCity: string;
    gender: string;
    birthDate: string;
    nationality: string;
    licenseNumber?: string | null;
    recaptchaToken: string;
}

/**
 * Demande de licence publique. Utilise l'API publique (sans authentification) :
 * soumission du formulaire puis dépôt du certificat médical via le token retourné.
 */
export class LicenseRepository {
    private api = usePublicApi();

    async submitRequest(body: SubmitLicenseRequestBody): Promise<License> {
        return await this.api<License>('/public/license-request', {
            method: 'POST',
            body,
        });
    }

    async uploadMedicalCertificate(token: string, file: File): Promise<License> {
        const formData = new FormData();
        formData.append('file', file);

        return await this.api<License>(`/public/license-request/${token}/medical-certificate`, {
            method: 'POST',
            body: formData,
        });
    }

    async getForPayment(token: string): Promise<LicensePaymentView> {
        return await this.api<LicensePaymentView>(`/public/license/${token}`, { method: 'GET' });
    }

    async createCheckout(token: string): Promise<{ redirectUrl: string | null }> {
        return await this.api<{ redirectUrl: string | null }>(`/public/license/${token}/checkout`, {
            method: 'POST',
        });
    }
}
