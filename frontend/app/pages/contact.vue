<template>
  <HomeContactSection />
</template>

<script setup lang="ts">
definePageMeta({ layout: 'public' })
useSeoMeta({
  title: 'Contact - Clapiers Volley Ball',
  description: 'Contactez le Clapiers Volley Ball pour rejoindre le club ou obtenir des informations sur les inscriptions. Répondez à vos questions via notre formulaire.',
  ogTitle: 'Contact - Clapiers Volley Ball',
  ogDescription: 'Contactez le Clapiers Volley Ball pour rejoindre le club ou obtenir des informations sur les inscriptions.',
  ogUrl: 'https://clapiersvb.fr/contact',
  twitterTitle: 'Contact - Clapiers Volley Ball',
  twitterDescription: 'Contactez le Clapiers Volley Ball pour rejoindre le club ou obtenir des informations sur les inscriptions.',
})
useHead({
  link: [{ rel: 'canonical', href: 'https://clapiersvb.fr/contact' }],
})
</script>
