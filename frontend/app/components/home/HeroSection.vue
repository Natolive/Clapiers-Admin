<script setup lang="ts">
import { NuxtLink } from '#components'

const { season, status: seasonStatus } = useCurrentSeason()
const { open, status: openStatus } = useInscriptionsStatus()

// Sur site statique, les données sont récupérées côté client (server:false) : on
// affiche un squelette tant que les deux appels ne sont pas résolus, pour éviter
// un flash « ouvertes » (défaut) qui basculerait ensuite en « clôturées ».
const settled = (s: string) => s === 'success' || s === 'error'
const badgeReady = computed(() => settled(seasonStatus.value) && settled(openStatus.value))
</script>

<template>
  <section id="accueil" class="hero">
    <div class="hero-bg">
      <div class="bg-shape shape-1"></div>
      <div class="bg-shape shape-2"></div>
      <div class="bg-pattern"></div>
    </div>

    <div class="hero-content">
      <div class="hero-text">
        <span v-if="!badgeReady" class="hero-badge hero-badge--skeleton" aria-hidden="true">
          <span class="badge-dot"></span>
          <span class="skeleton-text"></span>
        </span>
        <component
          v-else
          :is="open ? NuxtLink : 'span'"
          :to="open ? '/inscriptions' : undefined"
          class="hero-badge"
          :class="open ? 'is-open' : 'is-closed'"
        >
          <span class="badge-dot"></span>
          Inscriptions{{ season ? ' ' + season : '' }} {{ open ? 'ouvertes' : 'clôturées' }}
        </component>

        <h1 class="home-title">
          Clapiers
          <span class="text-accent">Volley Ball</span>
        </h1>

        <p class="home-subtitle">
          Rejoignez notre club convivial au coeur de l'Hérault.
          Dans une ambiance détendue, débutant ou confirmé,
          venez partager notre passion du volleyball loisir.
        </p>

        <div class="hero-actions">
          <NuxtLink to="/contact" class="btn-primary">
            <i class="pi pi-send"></i>
            Nous rejoindre
          </NuxtLink>
          <NuxtLink to="/horaires" class="btn-secondary">
            <i class="pi pi-calendar"></i>
            Voir les horaires
          </NuxtLink>
        </div>

        <div class="hero-stats">
          <div class="stat-item">
            <span class="stat-number">2</span>
            <span class="stat-label">Séances / semaine</span>
          </div>
          <div class="stat-divider"></div>
          <div class="stat-item">
            <span class="stat-number">2h</span>
            <span class="stat-label">De jeu</span>
          </div>
          <div class="stat-divider"></div>
          <div class="stat-item">
            <span class="stat-number">100%</span>
            <span class="stat-label">Plaisir</span>
          </div>
        </div>
      </div>

      <div class="hero-visual">
        <div class="visual-container">
          <div class="volleyball-wrapper">
            <img src="/logo.svg" alt="Clapiers Volley Ball" width="192" height="192" class="volleyball animate-float">
          
          </div>
          <div class="visual-card card-1">
            <i class="pi pi-users"></i>
            <span>Esprit d'équipe</span>
          </div>
          <div class="visual-card card-2">
            <i class="pi pi-heart"></i>
            <span>Passion</span>
          </div>
          <div class="visual-card card-3">
            <i class="pi pi-sun"></i>
            <span>Détente</span>
          </div>
        </div>
      </div>
    </div>

    <div class="scroll-indicator">
      <div class="scroll-line"></div>
      <span>Découvrir</span>
    </div>
  </section>
</template>

<style scoped>
.hero {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  justify-content: center;
  position: relative;
  overflow: hidden;
  background: var(--club-gradient);
  padding: 6rem 2rem 4rem;
}

.hero-bg {
  position: absolute;
  inset: 0;
  overflow: hidden;
}

.bg-shape {
  position: absolute;
  border-radius: 50%;
  opacity: 0.1;
}

.shape-1 {
  width: 600px;
  height: 600px;
  background: var(--club-secondary);
  top: -200px;
  right: -100px;
}

.shape-2 {
  width: 400px;
  height: 400px;
  background: var(--club-accent);
  bottom: -100px;
  left: -100px;
}

.bg-pattern {
  position: absolute;
  inset: 0;
  background-image: radial-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px);
  background-size: 40px 40px;
}

.hero-content {
  max-width: 1200px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4rem;
  align-items: center;
  position: relative;
  z-index: 1;
}

.hero-text {
  color: white;
}

.hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: rgba(255, 255, 255, 0.1);
  padding: 0.5rem 1rem;
  border-radius: 50px;
  font-size: 0.85rem;
  font-weight: 500;
  margin-bottom: 1.5rem;
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: #fff;
  text-decoration: none;
  transition: background 0.2s ease;
}

/* Ouvert : le badge est un lien vers l'inscription. */
a.hero-badge:hover {
  background: rgba(255, 255, 255, 0.18);
}

.badge-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  animation: pulse-soft 2s ease-in-out infinite;
}

.hero-badge.is-open .badge-dot {
  background: #22c55e;
}
.hero-badge.is-closed .badge-dot {
  background: #d4134f;
}

/* Squelette affiché tant que le statut n'est pas chargé (site statique). */
.hero-badge--skeleton .badge-dot {
  background: rgba(255, 255, 255, 0.4);
  animation: none;
}
.skeleton-text {
  display: inline-block;
  width: 160px;
  height: 0.9em;
  border-radius: 4px;
  background: linear-gradient(90deg,
    rgba(255, 255, 255, 0.12) 25%,
    rgba(255, 255, 255, 0.25) 37%,
    rgba(255, 255, 255, 0.12) 63%);
  background-size: 400% 100%;
  animation: skeleton-shimmer 1.4s ease infinite;
}
@keyframes skeleton-shimmer {
  0% { background-position: 100% 50%; }
  100% { background-position: 0 50%; }
}

.text-accent {
  display: block;
  color: var(--club-accent);
}

.hero-actions {
  display: flex;
  gap: 1rem;
  margin-top: 2.5rem;
  flex-wrap: wrap;
}

.hero-stats {
  display: flex;
  align-items: center;
  gap: 2rem;
  margin-top: 3rem;
  padding-top: 2rem;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.hero-stats .stat-item {
  text-align: left;
}

.hero-stats .stat-number {
  font-size: 2rem;
  font-weight: 800;
  color: white;
}

.hero-stats .stat-label {
  font-size: 0.85rem;
  color: rgba(255, 255, 255, 0.7);
  margin-top: 0.25rem;
}

.stat-divider {
  width: 1px;
  height: 40px;
  background: rgba(255, 255, 255, 0.2);
}

.hero-visual {
  display: flex;
  justify-content: center;
  align-items: center;
}

.visual-container {
  position: relative;
  width: 100%;
  max-width: 400px;
  aspect-ratio: 1;
}

.volleyball-wrapper {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.volleyball {
  width: 12rem;
  height: 12rem;
  object-fit: contain;
  filter: drop-shadow(0 20px 40px rgba(0, 0, 0, 0.3));
}

.visual-card {
  position: absolute;
  background: rgba(255, 255, 255, 0.95);
  padding: 0.75rem 1rem;
  border-radius: 12px;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 600;
  font-size: 0.85rem;
  color: var(--club-dark);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
  backdrop-filter: blur(10px);
}

.visual-card i {
  color: var(--club-secondary);
}

.card-1 {
  top: 15%;
  left: 0;
  transform: rotate(-3deg);
}

.card-2 {
  top: 50%;
  right: -10%;
  transform: rotate(3deg);
}

.card-3 {
  bottom: 15%;
  left: 10%;
  transform: rotate(-2deg);
}

.scroll-indicator {
  position: absolute;
  bottom: 2rem;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  color: rgba(255, 255, 255, 0.6);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.1em;
}

.scroll-line {
  width: 1px;
  height: 40px;
  background: linear-gradient(to bottom, transparent, rgba(255, 255, 255, 0.5));
  animation: scroll-bounce 2s ease-in-out infinite;
}

@keyframes scroll-bounce {
  0%, 100% { transform: scaleY(1); opacity: 1; }
  50% { transform: scaleY(0.6); opacity: 0.5; }
}

@media (max-width: 968px) {
  .hero-content {
    grid-template-columns: 1fr;
    text-align: center;
  }

  .hero-visual {
    display: none;
  }

  .hero-stats {
    justify-content: center;
  }

  .hero-actions {
    justify-content: center;
  }
}

@media (max-width: 480px) {
  .hero-stats {
    flex-direction: column;
    gap: 1.5rem;
  }

  .stat-divider {
    width: 60px;
    height: 1px;
  }

  .hero-stats .stat-item {
    text-align: center;
  }
}
</style>
