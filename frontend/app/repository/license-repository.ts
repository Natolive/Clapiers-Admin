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
    /**
     * Brouillon portant les pièces déjà déposées : elles sont rattachées au
     * membre par la soumission. Sa présence dispense du captcha, vérifié à
     * l'ouverture du brouillon.
     */
    draftToken?: string | null;
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
    /** Exigé seulement en l'absence de brouillon. */
    recaptchaToken?: string | null;
    /** true = a répondu NON à toutes les rubriques du questionnaire de santé. */
    healthDeclaration: boolean;
    // Représentant légal — uniquement si le membre est mineur.
    legalRepFirstName?: string | null;
    legalRepLastName?: string | null;
    legalRepEmail?: string | null;
    legalRepPhone?: string | null;
}

/** Slots médiathèque déposables à l'inscription. */
export type LicenseDocumentKey = 'identity_photo' | 'id_card' | 'medical_certificate' | 'attestation';

/** Une pièce déjà reçue par le serveur, telle que la reprise la renvoie. */
export interface DraftDocument {
    originalName: string;
    mimeType: string | null;
    size: number | null;
}

/**
 * Brouillon d'inscription : les champs saisis et les pièces déjà déposées,
 * avant que la demande existe. Le token permet de reprendre après une
 * déconnexion.
 */
export interface InscriptionDraft {
    token: string;
    payload: Record<string, any>;
    documents: Partial<Record<LicenseDocumentKey, DraftDocument>>;
}

/**
 * Demande de licence publique. Utilise l'API publique (sans authentification).
 *
 * Les pièces ne sont plus envoyées après la soumission mais déposées sur le
 * brouillon dès qu'elles sont choisies : une demande ne peut plus arriver sans
 * elles, et un envoi refusé se rejoue sans rien perdre.
 */
export class LicenseRepository {
    private api = usePublicApi();

    async submitRequest(body: SubmitLicenseRequestBody): Promise<License> {
        return await this.api<License>('/public/license-request', {
            method: 'POST',
            body,
        });
    }

    async createDraft(recaptchaToken: string): Promise<InscriptionDraft> {
        return await this.api<InscriptionDraft>('/public/inscription-draft', {
            method: 'POST',
            body: { recaptchaToken },
        });
    }

    async getDraft(token: string): Promise<InscriptionDraft> {
        return await this.api<InscriptionDraft>(`/public/inscription-draft/${token}`, { method: 'GET' });
    }

    async saveDraft(token: string, payload: Record<string, any>): Promise<InscriptionDraft> {
        return await this.api<InscriptionDraft>(`/public/inscription-draft/${token}`, {
            method: 'PUT',
            body: { payload },
        });
    }

    async deleteDraft(token: string): Promise<void> {
        await this.api(`/public/inscription-draft/${token}`, { method: 'DELETE' });
    }

    async uploadDraftDocument(token: string, systemKey: LicenseDocumentKey, file: File): Promise<InscriptionDraft> {
        const formData = new FormData();
        formData.append('file', file);

        return await this.api<InscriptionDraft>(`/public/inscription-draft/${token}/document/${systemKey}`, {
            method: 'POST',
            body: formData,
        });
    }

    async deleteDraftDocument(token: string, systemKey: LicenseDocumentKey): Promise<InscriptionDraft> {
        return await this.api<InscriptionDraft>(`/public/inscription-draft/${token}/document/${systemKey}`, {
            method: 'DELETE',
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
