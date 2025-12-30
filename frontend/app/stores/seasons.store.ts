import { defineStore } from 'pinia'
import { SeasonsRepository } from '~/repository/seasons-repository'
import type { Season } from '~/types/entity/Season'

export const useSeasonsStore = defineStore('seasons', () => {
    const actualSeason = ref<Season | null>(null)

    const hasActualSeason = computed(() => !!actualSeason.value)

    async function fetchActualSeason() {
        try {
            const seasonsRepository = new SeasonsRepository()
            actualSeason.value = await seasonsRepository.getActual()
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
