<template>
    <div class="cal-page-wrapper">
        <CalendarView
            :fetch-fn="fetchFn"
            :teams="teams"
            :user-team-id="userTeamId"
        />
    </div>
</template>

<script setup lang="ts">
import { GameRepository } from '~/repository/game-repository';
import { TeamRepository } from '~/repository/team-repository';
import { useAuthStore } from '~/stores/auth.store';
import type { Team } from '~/types/entity/Team';
import CalendarView from '~/components/calendar/CalendarView.vue';

definePageMeta({ middleware: 'auth-middleware', layout: 'dashboard' });
useHead({ title: 'Calendrier' });

const { isSuperAdmin } = useUserRole();
const authStore = useAuthStore();
const gameRepository = new GameRepository();
const teamRepository = new TeamRepository();

const teams = ref<Team[]>([]);
const userTeamId = computed(() => authStore.user?.member?.team?.id ?? null);

const fetchFn = ({ start, end, teamId }: { start: string; end: string; teamId?: number | null }) =>
    gameRepository.getAll({ start, end, teamId });

onMounted(async () => {
    if (isSuperAdmin.value) {
        teams.value = await teamRepository.getAll().catch(() => []);
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
