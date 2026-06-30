import type { Member } from '~/types/entity/Member';
import type { LicenseStatus } from '~/types/enum/LicenseStatus';

export type License = {
    id: number;
    member: Member;
    season: string;
    status: LicenseStatus;
    amount: number | null;
    helloAssoTierId: number | null;
    accessToken: string | null;
    tokenExpiresAt: string | null;
    medicalCertificateFileName: string | null;
    licenseNumber: string | null;
    approvedAt: string | null;
    rejectionReason: string | null;
    createdAt: string;
    updatedAt: string;
};
