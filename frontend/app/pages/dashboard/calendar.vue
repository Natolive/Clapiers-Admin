<template>
    <div class="cal-page-wrapper">
        <CalendarView
            :fetch-fn="fetchFn"
            :closure-fetch-fn="closureFetchFn"
            :teams="teams"
            :user-team-id="userTeamId"
        />
    </div>
</template>

<script setup lang="ts">
import { GameRepository } from '~/repository/game-repository';
import { TeamRepository } from '~/repository/team-repository';
import { SalleClosureRepository } from '~/repository/salle-closure-repository';
import { useAuthStore } from '~/stores/auth.store';
import type { Team } from '~/types/entity/Team';
import CalendarView from '~/components/calendar/CalendarView.vue';
import type { CalendarFetchFn } from '~/composables/useCalendarEvents';
import type { SalleClosureFetchFn } from '~/composables/useSalleClosures';

definePageMeta({ middleware: 'auth-middleware', layout: 'dashboard' });
useHead({ title: 'Calendrier' });

const { isSuperAdmin } = useUserRole();
const authStore = useAuthStore();
const toast = usePVToastService();
const gameRepository = new GameRepository();
const teamRepository = new TeamRepository();
const salleClosureRepository = new SalleClosureRepository();

const teams = ref<Team[]>([]);
const userTeamId = computed(() => authStore.user?.member?.team?.id ?? null);

const fetchFn: CalendarFetchFn = ({ start, end }) =>
    gameRepository.getAll({ start, end });

const closureFetchFn: SalleClosureFetchFn = () => salleClosureRepository.getAll();

onMounted(async () => {
    if (isSuperAdmin.value) {
        try {
            teams.value = await teamRepository.getAll();
        } catch {
            teams.value = [];
            toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les équipes', life: 4000 });
        }
    } else {
        const team = authStore.user?.member?.team;
        teams.value = team ? [team] : [];
    }
});
</script>

<style scoped>
/* 3.75rem topbar + 1.5rem top padding + 1.5rem bottom padding */
.cal-page-wrapper {
    height: calc(100vh - 3.75rem - 3rem);
}

@media (max-width: 768px) {
    .cal-page-wrapper {
        height: calc(100vh - 3.75rem - 1rem);
    }
}
</style>
