<template>
    <SkeletonLoader v-if="loading" type="card-grid" :count="6" />

    <div v-else class="grid">
      <div v-for="season in sortedSeasons" :key="season.id" class="col-12 md:col-6 lg:col-4">
        <Card>
          <template #title>
            <div class="flex align-items-center gap-2">
              <span>Saison {{ season.startYear }}/{{ season.endYear }}</span>
              <Badge v-if="season.isActual" value="Actuelle" severity="success" />
            </div>
          </template>
          <template #content>
            <div class="flex flex-column gap-3">
              <div class="flex align-items-center gap-2">
                <i class="pi pi-sitemap text-primary"></i>
                <span class="font-semibold">Équipes:</span>
                <span>{{ teamCounts[season.id] ?? 0 }}</span>
              </div>
              <div class="flex align-items-center gap-2">
                <i class="pi pi-id-card text-primary"></i>
                <span class="font-semibold">Licenciés:</span>
                <span>N/A</span>
              </div>
            </div>
          </template>
        </Card>
      </div>
    </div>
</template>

<script setup lang="ts">
import SkeletonLoader from '~/components/common/skeleton/SkeletonLoader.vue';
import { SeasonRepository } from '~/repository/season-repository';
import { TeamRepository } from '~/repository/team-repository';
import type { Season } from '~/types/entity/Season';

definePageMeta({
   middleware: 'auth-middleware',
  layout: 'dashboard'
});

const { isSuperAdmin } = useUserRole();
const seasonRepository = new SeasonRepository();
const teamRepository = new TeamRepository();
const seasons = ref<Season[]>([]);
const teamCounts = reactive<Record<number, number>>({});
const loading = ref(true);

// Computed property to sort seasons from most recent to oldest
const sortedSeasons = computed(() => {
  return [...seasons.value].sort((a, b) => b.startYear - a.startYear);
});

// Fetch seasons on mount
onMounted(async () => {
  // Redirect if not super admin (after auth is loaded)
  if (!isSuperAdmin.value) {
    await navigateTo('/dashboard');
    return;
  }

  try {
    seasons.value = await seasonRepository.getAll();

    // Fetch team counts for each season
    await Promise.all(
      seasons.value.map(async (season) => {
        const count = await teamRepository.countBySeason(season.id);
        teamCounts[season.id] = count;
      })
    );
  } finally {
    loading.value = false;
  }
});
</script>
