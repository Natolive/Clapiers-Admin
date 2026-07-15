<template>
  <div>
    <Card>
      <template #title>Logs</template>
      <template #subtitle>Journaux applicatifs (avertissements et erreurs). Rétention : 14 jours.</template>
      <template #content>
        <LogsDatatable />
      </template>
    </Card>
  </div>
</template>

<script setup lang="ts">
import LogsDatatable from '~/components/datatables/LogsDatatable.vue'
import { AppUserRole } from '~/types/entity/AppUser'

definePageMeta({
  middleware: 'auth-middleware',
  layout: 'dashboard',
  requiredRoles: [AppUserRole.SUPER_ADMIN],
  redirectTo: '/dashboard/calendar',
})

useHead({ title: 'Logs' })
</script>
