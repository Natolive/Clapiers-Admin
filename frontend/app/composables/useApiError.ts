/**
 * Message lisible pour une erreur d'appel API.
 *
 * `data.message` (refus métier) et `data.detail` (422 de validation Symfony)
 * n'existent que si la réponse vient bien de l'API. Un refus de l'ingress —
 * typiquement 413 quand `proxy-body-size` est plus bas que le plafond des
 * routes — renvoie du HTML, et une coupure réseau ne renvoie rien : sans repli
 * sur le statut, la personne ne voyait qu'« Une erreur est survenue » et le
 * backend, qui n'a jamais reçu la requête, n'en gardait aucune trace.
 */
export function apiErrorMessage(err: any): string {
    const data = err?.data ?? err?.response?._data;
    if (data && typeof data === 'object') {
        const message = data.message || data.detail;
        if (message) return message;
    }

    const status = err?.status ?? err?.statusCode ?? err?.response?.status;
    if (status === 413) {
        return 'Fichier refusé par le serveur : trop volumineux. Réduisez-le (photo en qualité moyenne, PDF allégé) et réessayez.';
    }
    if (!status) {
        return "Envoi interrompu — connexion perdue ou onglet mis en veille. Réessayez en restant sur la page.";
    }

    return `Le serveur a refusé l'envoi (erreur ${status}). Réessayez ; si cela persiste, signalez ce numéro au club.`;
}
