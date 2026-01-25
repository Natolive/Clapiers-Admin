<template>
  <div class="home-page">
    <HomeHeroSection />
    <HomeAboutSection />
    <HomeGallerySection />
    <HomeScheduleSection />
    <HomeContactSection />
  </div>
</template>

<script setup lang="ts">
definePageMeta({
  layout: 'public'
})

useHead({
  title: 'VBC Clapiers - Volleyball Club de Clapiers',
  meta: [
    {
      name: 'description',
      content: 'Rejoignez le Volleyball Club de Clapiers. Loisir ou compétition, débutant ou confirmé, venez partager notre passion du volleyball.'
    }
  ]
})
</script>

<style>
@import '~/assets/css/home.css';
</style>
