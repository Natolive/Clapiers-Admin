<template>
  <div>
    <h2 class="text-3xl font-bold mb-4">Calendrier</h2>

    <!-- Content will be added here -->
  </div>
</template>

<script setup lang="ts">
definePageMeta({
   middleware: 'auth-middleware',
  layout: 'dashboard'
});
</script>
