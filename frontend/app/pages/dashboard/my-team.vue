<template>
  <div>
    <h2 class="text-3xl font-bold mb-4">Mon équipe</h2>

    <!-- Content will be added here -->
  </div>
</template>

<script setup lang="ts">
definePageMeta({
   middleware: 'auth-middleware',
  layout: 'dashboard'
});

const { isAdmin } = useUserRole();

// Redirect if not admin (ADMIN or SUPER_ADMIN)
if (!isAdmin.value) {
  await navigateTo('/dashboard');
}
</script>
