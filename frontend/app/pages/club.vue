<template>
  <HomeAboutSection />
</template>

<script setup lang="ts">
definePageMeta({ layout: 'public' })
useSeoMeta({
  title: 'Le Club - Clapiers Volley Ball',
  description: 'Découvrez le Clapiers Volley Ball, un club ouvert à tous les niveaux. Ambiance conviviale, entraînements réguliers, tournois et événements à Clapiers (34830).',
  ogTitle: 'Le Club - Clapiers Volley Ball',
  ogDescription: 'Découvrez le Clapiers Volley Ball, un club ouvert à tous les niveaux. Ambiance conviviale, entraînements réguliers, tournois et événements à Clapiers (34830).',
  ogUrl: 'https://clapiersvb.fr/club',
  twitterTitle: 'Le Club - Clapiers Volley Ball',
  twitterDescription: 'Découvrez le Clapiers Volley Ball, un club ouvert à tous les niveaux. Ambiance conviviale, entraînements réguliers et événements à Clapiers.',
})
useHead({
  link: [{ rel: 'canonical', href: 'https://clapiersvb.fr/club' }],
})
</script>
