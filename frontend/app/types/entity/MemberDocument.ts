import { MemberDocumentType } from '~/types/enum/MemberDocumentType';

/** Nœud de la médiathèque d'un membre (arbre dossiers/documents). */
export type MemberDocument = {
    /** UUID public (identifiant non énumérable utilisé dans les URLs). */
    id: string;
    parentId: string | null;
    type: MemberDocumentType;
    name: string;
    season: string | null;
    systemKey: string | null;
    /** Nœud par défaut : ni renommable ni supprimable. */
    protected: boolean;
    hasFile: boolean;
    originalName: string | null;
    mimeType: string | null;
    size: number | null;
    /** URL CDN signée du fichier (null si le nœud n'en porte pas). */
    url: string | null;
    children: MemberDocument[];
    createdAt: string;
    updatedAt: string;
};
