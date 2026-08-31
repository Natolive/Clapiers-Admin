/**
 * Saison sportive courante (réglage admin), lue via l'API publique.
 *
 * Mise en cache par le système Nuxt (`useAsyncData` + `getCachedData`) : une
 * seule requête réseau, partagée entre tous les composants et réutilisée lors
 * des navigations. `server: false` → jamais figée au prérendu, toujours fraîche
 * (le réglage peut changer côté admin sans rebuild).
 */
export const useCurrentSeason = () => {
    const api = usePublicApi()
    const { data: season, refresh, status } = useAsyncData(
        'public-season',
        () => api<{ season: string }>('/public/season').then((r) => r.season),
        {
            server: false,
            default: () => '',
            getCachedData: (key, nuxtApp) => nuxtApp.payload.data[key] ?? nuxtApp.static.data[key],
        },
    )

    // Rétro-compat : anciens appelants faisaient `onMounted(fetchSeason)`.
    // Le fetch est désormais automatique ; `fetchSeason` force un rafraîchissement.
    return { season, fetchSeason: refresh, status }
}
