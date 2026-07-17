<template>
  <PublicPage
    label="Notre club"
    title="Le Club"
    subtitle="Depuis plus de 25 ans, Clapiers Volley Ball accueille tous les passionnés de volleyball dans une ambiance conviviale et sportive."
    wide
  >
    <section class="pp-block">
      <div class="features-grid">
        <div v-for="f in features" :key="f.title" class="feature-card">
          <div class="feature-icon" :class="f.tone">
            <i class="pi" :class="f.icon"></i>
          </div>
          <h3 class="feature-title">{{ f.title }}</h3>
          <p class="feature-desc">{{ f.desc }}</p>
        </div>
      </div>
    </section>
  </PublicPage>
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

const features = [
  { icon: 'pi-users', tone: 'primary', title: 'Tous niveaux', desc: 'Débutant ou joueur confirmé, chacun trouve sa place et progresse à son rythme.' },
  { icon: 'pi-heart', tone: 'accent', title: '100% Loisir', desc: 'Pas de pression, juste du plaisir. Venez vous défouler dans une ambiance détendue et bienveillante.' },
  { icon: 'pi-map-marker', tone: 'blue', title: 'Gymnase de qualité', desc: 'Entraînements au gymnase municipal de Clapiers, un équipement moderne et bien entretenu.' },
  { icon: 'pi-calendar', tone: 'primary', title: 'Événements réguliers', desc: 'Tournois, repas du club, sorties... La vie associative est au cœur de notre fonctionnement.' },
  { icon: 'pi-users', tone: 'accent', title: 'Convivialité', desc: 'Plus qu\'un club de sport, une vraie communauté. L\'entraide et la bonne humeur sont au rendez-vous.' },
  { icon: 'pi-euro', tone: 'blue', title: 'Tarifs accessibles', desc: 'Des cotisations adaptées pour rendre le volleyball accessible au plus grand nombre.' },
]
</script>

<style scoped>
.features-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.25rem;
}

.feature-card {
  background: var(--club-light);
  border: 1px solid rgba(0, 0, 0, 0.05);
  border-radius: 12px;
  padding: 1.5rem;
}

.feature-icon {
  width: 3rem;
  height: 3rem;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  color: #fff;
  margin-bottom: 1rem;
}
.feature-icon.primary { background: var(--club-primary); }
.feature-icon.accent { background: var(--club-accent); }
.feature-icon.blue { background: #2d5a87; }

.feature-title {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--club-dark);
  margin: 0 0 0.5rem;
}

.feature-desc {
  color: #666;
  font-size: 0.95rem;
  line-height: 1.6;
  margin: 0;
}

@media (max-width: 968px) {
  .features-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 600px) {
  .features-grid { grid-template-columns: 1fr; }
}
</style>
