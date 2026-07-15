/**
 * Saison sportive courante (réglage admin), lue via l'API publique.
 * Valeur partagée entre composants (useState) : un seul appel réseau.
 */
export const useCurrentSeason = () => {
    const season = useState<string>('current-season', () => '')

    const fetchSeason = async (): Promise<void> => {
        if (season.value) return
        try {
            const res = await usePublicApi()<{ season: string }>('/public/season')
            season.value = res.season
        } catch {
            // silencieux : l'affichage de la saison est non-bloquant
        }
    }

    return { season, fetchSeason }
}
