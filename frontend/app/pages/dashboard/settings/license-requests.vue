<template>
  <div>
    <Card>
      <template #title>Demandes de licence</template>
      <template #subtitle>Validez ou refusez les demandes, puis envoyez le lien de paiement.</template>
      <template #content>
        <LicenseRequestsDatatable />
      </template>
    </Card>
  </div>
</template>

<script setup lang="ts">
import LicenseRequestsDatatable from '~/components/datatables/LicenseRequestsDatatable.vue'
import { AppUserRole } from '~/types/entity/AppUser'

definePageMeta({
  middleware: 'auth-middleware',
  layout: 'dashboard',
  requiredRoles: [AppUserRole.SUPER_ADMIN],
  redirectTo: '/dashboard/calendar',
})

useHead({ title: 'Demandes de licence' })
</script>
