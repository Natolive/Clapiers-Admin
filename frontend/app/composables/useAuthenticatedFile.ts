/**
 * Fichiers servis par des routes authentifiées (en-tête Bearer) : ni un `href`
 * ni un `<img src>` ne peuvent les atteindre, il faut passer par un blob.
 * Centralise le fetch pour les deux usages : télécharger et visualiser.
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

    /**
     * Ouvre le fichier dans un onglet : visionneuse PDF / image du navigateur,
     * rien à embarquer. L'onglet est ouvert AVANT le fetch, sinon les bloqueurs
     * de pop-up refusent un window.open survenant après un await.
     */
    const view = async (path: string): Promise<void> => {
        const tab = window.open('', '_blank');
        if (!tab) {
            throw new Error('Autorisez les pop-ups pour prévisualiser le fichier.');
        }

        try {
            const blobUrl = URL.createObjectURL(await fetchBlob(path));
            tab.location.href = blobUrl;
            // Révoqué tard : l'onglet doit avoir eu le temps de charger le blob.
            setTimeout(() => URL.revokeObjectURL(blobUrl), 60_000);
        } catch (e) {
            tab.close();
            throw e;
        }
    };

    return { download, view };
}
