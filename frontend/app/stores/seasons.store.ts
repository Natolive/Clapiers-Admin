import { defineStore } from 'pinia'
import { SeasonRepository } from '~/repository/season-repository'
import type { Season } from '~/types/entity/Season'

export const useSeasonsStore = defineStore('seasons', () => {
    const actualSeason = ref<Season | null>(null)

    const hasActualSeason = computed(() => !!actualSeason.value)

    async function fetchActualSeason() {
        try {
            const seasonRepository = new SeasonRepository()
            actualSeason.value = await seasonRepository.getActual()
        } catch (error) {
            actualSeason.value = null
        }
    }

    function clearActualSeason() {
        actualSeason.value = null
    }

    return {
        actualSeason,
        hasActualSeason,
        fetchActualSeason,
        clearActualSeason
    }
})
