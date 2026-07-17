<template>
  <div class="pp-page">
    <div :class="['pp-container', { 'pp-container--wide': wide }]">
      <header class="pp-head">
        <span v-if="label" class="pp-label">{{ label }}</span>
        <h1 class="pp-title">{{ title }}</h1>
        <p v-if="subtitle" class="pp-subtitle">{{ subtitle }}</p>
      </header>
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{ label?: string; title: string; subtitle?: string; wide?: boolean }>()
</script>
