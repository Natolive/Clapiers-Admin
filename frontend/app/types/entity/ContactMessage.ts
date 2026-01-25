import type { AppUser } from './AppUser';

export type ContactMessage = {
    id: number;
    firstName: string;
    lastName: string;
    email: string;
    subject: string;
    message: string;
    isRead: boolean;
    readBy: AppUser | null;
    readAt: string | null;
    createdAt: string;
    updatedAt: string;
};
