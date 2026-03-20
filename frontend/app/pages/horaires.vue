<template>
  <HomeScheduleSection />
</template>

<script setup lang="ts">
definePageMeta({ layout: 'public' })
useSeoMeta({
  title: 'Horaires et lieu - Clapiers Volley Ball',
  description: 'Entraînements du Clapiers Volley Ball les lundis et vendredis de 20h30 à 22h30 au Gymnase Joël Abati, 1 Rue du Paraguay, 34830 Clapiers.',
  ogTitle: 'Horaires et lieu - Clapiers Volley Ball',
  ogDescription: 'Entraînements les lundis et vendredis de 20h30 à 22h30 au Gymnase Joël Abati, 1 Rue du Paraguay, 34830 Clapiers.',
  ogUrl: 'https://clapiersvb.fr/horaires',
  twitterTitle: 'Horaires et lieu - Clapiers Volley Ball',
  twitterDescription: 'Entraînements les lundis et vendredis de 20h30 à 22h30 au Gymnase Joël Abati, Clapiers.',
})
useHead({
  link: [{ rel: 'canonical', href: 'https://clapiersvb.fr/horaires' }],
})
</script>
