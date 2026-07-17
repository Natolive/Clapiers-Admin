<template>
  <Select
    v-if="isSuperAdmin"
    v-model="selected"
    :options="seasons"
    class="season-select"
  >
    <template #value="{ value }">
      <span class="season-select__value"><i class="pi pi-calendar" /><span class="season-select__word">Saison </span>{{ value || '—' }}</span>
    </template>
    <template #option="{ option }">Saison {{ option }}</template>
  </Select>
</template>

<script setup lang="ts">
import { useSeasonFilter } from '~/composables/useSeasonFilter'

const { selected, seasons, load } = useSeasonFilter()
const { isSuperAdmin } = useUserRole()
onMounted(() => { if (isSuperAdmin.value) load() })
</script>

<style scoped>
.season-select__value {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  white-space: nowrap;
}

@media (max-width: 767px) {
  .season-select {
    font-size: 0.75rem;
  }
  .season-select :deep(.p-select-label) {
    padding: 0.35rem 0.5rem;
  }
  /* Le pictogramme calendrier suffit : on retire le mot pour gagner de la place. */
  .season-select__word {
    display: none;
  }
}
</style>
