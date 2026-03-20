<template>
  <div class="home-page">
    <HomeHeroSection />
    <HomeGamesSection />
  </div>
</template>

<script setup lang="ts">
definePageMeta({
  layout: 'public'
})

useSeoMeta({
  title: 'Clapiers Volley Ball - Club de volleyball à Clapiers',
  description: 'Rejoignez le Clapiers Volley Ball. Loisir ou compétition, débutant ou confirmé, venez partager notre passion du volleyball.',
  ogTitle: 'Clapiers Volley Ball - Club de volleyball à Clapiers',
  ogDescription: 'Rejoignez le Clapiers Volley Ball. Loisir ou compétition, débutant ou confirmé, venez partager notre passion du volleyball.',
  ogUrl: 'https://clapiersvb.fr/',
  twitterTitle: 'Clapiers Volley Ball - Club de volleyball à Clapiers',
  twitterDescription: 'Rejoignez le Clapiers Volley Ball. Loisir ou compétition, débutant ou confirmé, venez partager notre passion du volleyball.',
})
useHead({
  link: [{ rel: 'canonical', href: 'https://clapiersvb.fr/' }],
})
</script>

<style>
@import '~/assets/css/home.css';
</style>
