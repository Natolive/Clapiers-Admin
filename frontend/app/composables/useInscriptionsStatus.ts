/**
 * Statut des inscriptions (réglages admin), lu via l'API publique :
 *  - `open` : affichage « ouvertes / clôturées » sur le site, indicatif ;
 *  - `formOpen` : réception réelle des demandes (formulaire d'inscription).
 *
 * Mis en cache par le système Nuxt (`useAsyncData` + `getCachedData`) : une
 * seule requête, partagée entre composants et réutilisée en navigation.
 * `server: false` → toujours frais (non figé au prérendu). Ouvert par défaut.
 */
export interface InscriptionsStatus {
    open: boolean
    formOpen: boolean
}

export const useInscriptionsStatus = () => {
    const api = usePublicApi()
    const { data, refresh, status } = useAsyncData<InscriptionsStatus>(
        'public-inscriptions-open',
        () => api<InscriptionsStatus>('/public/inscriptions-status'),
        {
            server: false,
            default: () => ({ open: true, formOpen: true }),
            getCachedData: (key, nuxtApp) => nuxtApp.payload.data[key] ?? nuxtApp.static.data[key],
        },
    )

    // Refs inscriptibles : les réglages écrivent dedans pour propager
    // l'affichage public sans rechargement.
    const flag = (key: keyof InscriptionsStatus) => computed({
        get: () => data.value?.[key] ?? true,
        set: (value: boolean) => { if (data.value) data.value[key] = value },
    })

    return { open: flag('open'), formOpen: flag('formOpen'), fetchStatus: refresh, status }
}
