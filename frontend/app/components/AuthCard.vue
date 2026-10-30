<template>
  <div class="flex align-items-center justify-content-center min-h-screen bg-surface-50 dark:bg-surface-950 px-4">
    <div class="surface-card p-6 shadow-2 border-round w-full" style="max-width: 450px">

      <div class="text-center mb-5">
        <img :src="isDark ? '/logo.svg' : '/logo-light.svg'" alt="Clapiers Volley Ball" height="100" class="mb-3">
        <h1 class="text-3xl font-medium mb-3">{{ title }}</h1>
        <span v-if="subtitle" class="text-600 font-medium">{{ subtitle }}</span>
      </div>

      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
// Carte des pages de compte (connexion, mot de passe oublié, invitation) :
// un seul design, calé sur le thème admin enregistré.
defineProps<{ title: string; subtitle?: string }>()

// Le logo bascule sur une variante à texte sombre en thème clair.
const { isDark } = useDarkMode()
</script>
