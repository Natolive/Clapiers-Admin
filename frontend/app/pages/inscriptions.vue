<template>
  <div class="coming-soon-page">
    <div class="container">
      <div class="content">
        <i class="pi pi-clock icon"></i>
        <h1>Inscriptions</h1>
        <p>Cette page est en cours de remplissage. Revenez bientôt !</p>
        <NuxtLink to="/" class="back-link">← Retour à l'accueil</NuxtLink>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'public' })
useSeoMeta({
  title: 'Inscriptions - Clapiers Volley Ball',
  description: 'Informations sur les inscriptions au Clapiers Volley Ball.',
})
</script>

<style scoped>
.coming-soon-page {
  min-height: 60vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
}

.container {
  max-width: 600px;
  margin: 0 auto;
  text-align: center;
}

.content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.5rem;
}

.icon {
  font-size: 4rem;
  color: var(--club-accent);
  opacity: 0.8;
}

h1 {
  font-size: 2.5rem;
  font-weight: 700;
  color: var(--club-dark);
  margin: 0;
}

p {
  font-size: 1.1rem;
  color: #6b7280;
  margin: 0;
}

.back-link {
  display: inline-block;
  margin-top: 0.5rem;
  color: var(--club-primary);
  text-decoration: none;
  font-weight: 500;
  transition: color 0.2s ease;
}

.back-link:hover {
  color: var(--club-accent);
}
</style>
