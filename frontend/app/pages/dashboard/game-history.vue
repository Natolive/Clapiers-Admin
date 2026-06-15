<template>
  <div class="history-page">

    <!-- Header: team filter + total -->
    <div class="page-header">
      <Select
        v-model="teamId"
        :options="teamOptions"
        option-label="name"
        option-value="id"
        placeholder="Toutes les équipes"
        show-clear
        class="team-filter"
      />

      <span v-if="!loading" class="stat">
        <i class="pi pi-history"></i>
        {{ total }} transaction{{ total > 1 ? 's' : '' }}
      </span>
    </div>

    <SkeletonLoader v-if="loading" type="list" />

    <template v-else>
      <GameHistoryList :entries="entries" :empty-label="emptyLabel" />

      <!-- Infinite scroll sentinel -->
      <div ref="sentinel" class="scroll-sentinel" aria-hidden="true">
        <i v-if="loadingMore" class="pi pi-spin pi-spinner"></i>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import SkeletonLoader from '~/components/common/skeleton/SkeletonLoader.vue';
import GameHistoryList from '~/components/game-history/GameHistoryList.vue';
import { TeamRepository } from '~/repository/team-repository';
import { AppUserRole } from '~/types/entity/AppUser';
import type { Team } from '~/types/entity/Team';

definePageMeta({
  middleware: 'auth-middleware',
  layout: 'dashboard',
  requiredRoles: [AppUserRole.SUPER_ADMIN],
  redirectTo: '/dashboard/calendar',
});

useHead({ title: 'Historique des matchs' });

const toast = usePVToastService();
const teamRepository = new TeamRepository();

const { entries, total, teamId, loading, loadingMore, loadFirstPage, loadMore } = useGameHistory();

const teamOptions = ref<Team[]>([]);

const sentinel = ref<HTMLElement | null>(null);
const { recheck } = useInfiniteScroll(sentinel, loadMore);

watch(() => entries.value.length, () => nextTick(recheck), { flush: 'post' });

const emptyLabel = computed(() =>
  teamId.value ? 'Aucune transaction pour cette équipe' : 'Aucune transaction enregistrée'
);

onMounted(async () => {
  await loadFirstPage();
  try {
    teamOptions.value = await teamRepository.getAll();
  } catch {
    teamOptions.value = [];
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les équipes', life: 4000 });
  }
});
</script>

<style scoped>
.history-page { padding: 0; }

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.5rem;
  animation: slide-down 0.3s ease;
}

@keyframes slide-down {
  from { opacity: 0; transform: translateY(-8px); }
  to { opacity: 1; transform: translateY(0); }
}

.team-filter {
  flex: 1;
  max-width: 18rem;
}

.stat {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  background: var(--p-surface-100);
  border-radius: 8px;
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
  white-space: nowrap;
  flex-shrink: 0;
}

.stat i { font-size: 1rem; }

.scroll-sentinel {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 3rem;
  color: var(--p-text-muted-color);
  font-size: 1.125rem;
}

@media (max-width: 640px) {
  .page-header { margin-bottom: 1rem; }
  .team-filter { max-width: none; }
  .stat { padding: 0.375rem 0.625rem; font-size: 0.8125rem; }
}
</style>
