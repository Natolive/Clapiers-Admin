/**
 * Fichiers *produits* par une route authentifiée (l'export xlsx/zip) : un
 * `href` ne peut pas porter le Bearer, il faut passer par un blob.
 *
 * Les pièces de la médiathèque, elles, ne transitent plus par l'API : voir
 * `useCdnFile()`.
 */
export function useAuthenticatedFile() {
    const fetchBlob = async (path: string): Promise<Blob> => {
        const config = useRuntimeConfig();
        const token = useCookie('auth_token').value;

        const res = await fetch(`${config.public.apiBase}${path}`, {
            headers: token ? { Authorization: `Bearer ${token}` } : {},
        });
        if (!res.ok) {
            throw new Error(`Fichier indisponible (HTTP ${res.status})`);
        }

        return await res.blob();
    };

    /** Enregistre le fichier sous `fileName`. */
    const download = async (path: string, fileName: string): Promise<void> => {
        const blobUrl = URL.createObjectURL(await fetchBlob(path));
        const a = document.createElement('a');
        a.href = blobUrl;
        a.download = fileName;
        a.click();
        URL.revokeObjectURL(blobUrl);
    };

    return { download };
}
