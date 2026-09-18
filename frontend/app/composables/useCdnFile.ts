/**
 * Pièces de la médiathèque : l'API ne les streame plus, elle renvoie une URL
 * CDN signée (Bunny Token Authentication) dans le payload. Le navigateur va la
 * chercher au bord, sans repasser par le backend.
 *
 * `download()` passe par un blob pour poser le nom d'origine : un attribut
 * `download` est ignoré sur un lien cross-origin. La pull zone doit donc
 * autoriser l'origine du dashboard (Bunny → Pull Zone → Headers → CORS).
 */
export function useCdnFile() {
    /** Nouvel onglet : visionneuse PDF / image du navigateur, rien à embarquer. */
    const open = (url: string): void => {
        window.open(url, '_blank', 'noopener');
    };

    const download = async (url: string, fileName: string): Promise<void> => {
        const res = await fetch(url);
        if (!res.ok) {
            throw new Error(`Fichier indisponible (HTTP ${res.status})`);
        }

        const blobUrl = URL.createObjectURL(await res.blob());
        const a = document.createElement('a');
        a.href = blobUrl;
        a.download = fileName;
        a.click();
        URL.revokeObjectURL(blobUrl);
    };

    return { open, download };
}
