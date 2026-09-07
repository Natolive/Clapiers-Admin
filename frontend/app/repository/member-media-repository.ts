import type { MemberDocument } from '~/types/entity/MemberDocument';

/** Accès API à la médiathèque d'un membre (dossiers/documents par saison). */
export class MemberMediaRepository {
    private api = useApi();

    /** Arbre complet (racines + enfants). Crée à la demande le mapping par défaut. */
    async getTree(memberId: number): Promise<MemberDocument[]> {
        return await this.api<MemberDocument[]>(`/member/${memberId}/media`, { method: 'GET' });
    }

    async createFolder(memberId: number, name: string, parentId: string | null = null): Promise<MemberDocument> {
        return await this.api<MemberDocument>(`/member/${memberId}/media/folder`, {
            method: 'POST',
            body: { name, parentId },
        });
    }

    async createDocument(memberId: number, name: string, file: File, parentId: string | null = null): Promise<MemberDocument> {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('name', name);
        if (parentId) formData.append('parentId', parentId);
        return await this.api<MemberDocument>(`/member/${memberId}/media/document`, {
            method: 'POST',
            body: formData,
        });
    }

    async uploadFile(memberId: number, nodeId: string, file: File): Promise<MemberDocument> {
        const formData = new FormData();
        formData.append('file', file);
        return await this.api<MemberDocument>(`/member/${memberId}/media/node/${nodeId}/file`, {
            method: 'POST',
            body: formData,
        });
    }

    async deleteFile(memberId: number, nodeId: string): Promise<MemberDocument> {
        return await this.api<MemberDocument>(`/member/${memberId}/media/node/${nodeId}/file`, {
            method: 'DELETE',
        });
    }

    async rename(memberId: number, nodeId: string, name: string): Promise<MemberDocument> {
        return await this.api<MemberDocument>(`/member/${memberId}/media/node/${nodeId}`, {
            method: 'PATCH',
            body: { name },
        });
    }

    async deleteNode(memberId: number, nodeId: string): Promise<{ id: string; deleted: boolean }> {
        return await this.api<{ id: string; deleted: boolean }>(`/member/${memberId}/media/node/${nodeId}`, {
            method: 'DELETE',
        });
    }

    /** Télécharge le fichier d'un document via un blob authentifié. */
    async download(memberId: number, nodeId: string, fileName: string): Promise<void> {
        await useAuthenticatedFile().download(this.filePath(memberId, nodeId), fileName);
    }

    /** Ouvre le fichier dans un onglet (même route authentifiée). */
    async view(memberId: number, nodeId: string): Promise<void> {
        await useAuthenticatedFile().view(this.filePath(memberId, nodeId));
    }

    private filePath(memberId: number, nodeId: string): string {
        return `/member/${memberId}/media/node/${nodeId}/download`;
    }
}
