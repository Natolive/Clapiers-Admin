<template>
  <div>
    <h2 class="text-3xl font-bold mb-4">Utilisateurs</h2>

    <!-- Content will be added here -->
  </div>
</template>

<script setup lang="ts">
import { AppUserRole } from '~/types/entity/AppUser';

definePageMeta({
  layout: 'dashboard'
});

const { isSuperAdmin } = useUserRole();

if (!isSuperAdmin.value) {
  await navigateTo('/dashboard');
}
</script>
