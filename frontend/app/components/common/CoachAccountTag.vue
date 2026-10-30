<template>
  <Tag
    :value="display.label"
    :severity="display.severity"
    :icon="display.icon"
    class="text-xs coach-tag"
    v-tooltip.top="'Cliquer pour modifier ou relancer l\'invitation'"
  />
</template>

<script setup lang="ts">
import type { InvitationStatus } from '~/types/entity/AppUser';

const props = defineProps<{ status: InvitationStatus }>();

const display = computed(() => ({
  invited: { label: 'Invitation en cours', severity: 'info', icon: 'pi pi-clock' },
  expired: { label: 'Invitation expirée', severity: 'warn', icon: 'pi pi-exclamation-triangle' },
  active: { label: "Chef d'équipe", severity: 'success', icon: 'pi pi-check' },
}[props.status]));
</script>

<style scoped>
.coach-tag { cursor: pointer; white-space: nowrap; }
</style>
