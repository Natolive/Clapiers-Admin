<template>
  <h1> Welcome to dashboard </h1>
</template>

<script setup lang="ts">
definePageMeta({
  middleware: 'auth-middleware'
})
</script>