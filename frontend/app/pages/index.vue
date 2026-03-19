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

useHead({
  title: 'Clapiers Volley Ball',
  meta: [
    {
      name: 'description',
      content: 'Rejoignez le Clapiers Volley Ball. Loisir ou compétition, débutant ou confirmé, venez partager notre passion du volleyball.'
    }
  ]
})
</script>

<style>
@import '~/assets/css/home.css';
</style>
