/**
 * Statut d'ouverture des inscriptions (réglage admin), lu via l'API publique.
 *
 * Mis en cache par le système Nuxt (`useAsyncData` + `getCachedData`) : une
 * seule requête, partagée entre composants et réutilisée en navigation.
 * `server: false` → toujours frais (non figé au prérendu). Ouvert par défaut.
 */
export const useInscriptionsStatus = () => {
    const api = usePublicApi()
    const { data: open, refresh, status } = useAsyncData(
        'public-inscriptions-open',
        () => api<{ open: boolean }>('/public/inscriptions-status').then((r) => r.open),
        {
            server: false,
            default: () => true,
            getCachedData: (key, nuxtApp) => nuxtApp.payload.data[key] ?? nuxtApp.static.data[key],
        },
    )

    return { open, fetchStatus: refresh, status }
}
