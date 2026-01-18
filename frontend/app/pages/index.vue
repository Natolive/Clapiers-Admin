<template>
  <div class="vitrine-page">
    <HeroSection />
    <AboutSection />
    <ContactSection />
  </div>
</template>

<script setup lang="ts">
import HeroSection from '~/components/vitrine/HeroSection.vue'
import AboutSection from '~/components/vitrine/AboutSection.vue'
import ContactSection from '~/components/vitrine/ContactSection.vue'

definePageMeta({
  layout: 'vitrine'
})

useHead({
  title: 'Clapiers Volleyball - Club de Volleyball à Clapiers',
  meta: [
    {
      name: 'description',
      content: 'Un short, une paire de chaussure et l\'aventure démarre, le reste c\'est nous qui nous en occupons dans la joie et la bonne humeur ! Vous êtes les bienvenues gratuitement les premières séances de découvertes, vous serez guidés et encadrés dans une ambiance conviviale.'
    },
    {
      property: 'og:title',
      content: 'Clapiers Volleyball - Club de Volleyball à Clapiers'
    },
    {
      property: 'og:description',
      content: 'Un short, une paire de chaussure et l\'aventure démarre, le reste c\'est nous qui nous en occupons dans la joie et la bonne humeur ! Vous êtes les bienvenues gratuitement les premières séances de découvertes, vous serez guidés et encadrés dans une ambiance conviviale.'
    }
  ]
})  
</script>

<style scoped>
.vitrine-page {
  scroll-behavior: smooth;
}
</style>