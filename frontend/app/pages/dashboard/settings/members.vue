<template>
  <div>
    <h2 class="text-3xl font-bold mb-4">Licenciés</h2>

    <!-- Content will be added here -->
  </div>
</template>

<script setup lang="ts">
definePageMeta({
  layout: 'dashboard'
});

const { isSuperAdmin } = useUserRole();

// Redirect if not super admin
if (!isSuperAdmin.value) {
  await navigateTo('/dashboard');
}
</script>
