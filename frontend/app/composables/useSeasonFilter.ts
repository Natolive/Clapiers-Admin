import { SettingRepository } from '~/repository/setting-repository'

/**
 * Saison sélectionnée pour filtrer le dashboard ET la liste des licenciés.
 * État partagé (useState) : changer la saison sur une vue la change sur l'autre.
 * Défaut : la saison courante. La liste vient de la table `season` (API réglages).
 */
export const useSeasonFilter = () => {
    const selected = useState<string>('season-filter', () => '')
    const seasons = useState<string[]>('season-list', () => [])

    const load = async (): Promise<void> => {
        if (seasons.value.length) return
        const s = await new SettingRepository().getSeason()
        seasons.value = s.seasons
        if (!selected.value) selected.value = s.season
    }

    return { selected, seasons, load }
}
