<template>
  <div>
    <div v-if="loading" class="flex justify-content-center align-items-center" style="min-height: 200px;">
      <i class="pi pi-spin pi-spinner" style="font-size: 2rem;"></i>
    </div>

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
                <span>N/A</span>
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
  </div>
</template>

<script setup lang="ts">
import { SeasonsRepository } from '~/repository/seasons-repository';
import type { Season } from '~/types/entity/Season';

definePageMeta({
  layout: 'dashboard'
});

const { isSuperAdmin } = useUserRole();

// Redirect if not super admin
if (!isSuperAdmin.value) {
  await navigateTo('/dashboard');
}

const seasonsRepository = new SeasonsRepository();
const seasons = ref<Season[]>([]);
const loading = ref(true);

// Computed property to sort seasons from most recent to oldest
const sortedSeasons = computed(() => {
  return [...seasons.value].sort((a, b) => b.startYear - a.startYear);
});

// Fetch seasons on mount
onMounted(async () => {
  try {
    seasons.value = await seasonsRepository.getAll();
  } finally {
    loading.value = false;
  }
});
</script>
