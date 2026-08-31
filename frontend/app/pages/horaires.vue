<template>
  <PublicPage
    label="Horaires & Lieu"
    title="Quand et où nous retrouver"
    subtitle="Nos créneaux d'entraînement et l'accès au gymnase."
    wide
  >
    <div v-if="season" class="season-notice">
      <i class="pi pi-calendar"></i>
      <span>Saison en cours&nbsp;: <strong>{{ season }}</strong></span>
    </div>

    <div class="pp-cols">
      <section class="pp-block">
        <h2 class="pp-block__title"><i class="pi pi-calendar"></i> Créneaux d'entraînement</h2>
        <div class="schedule-item">
          <div class="day-badge">Lun</div>
          <div class="schedule-details">
            <span class="schedule-day">Lundi</span>
            <span class="schedule-time">20h30 - 22h30</span>
          </div>
        </div>
        <div class="schedule-item">
          <div class="day-badge">Ven</div>
          <div class="schedule-details">
            <span class="schedule-day">Vendredi</span>
            <span class="schedule-time">20h30 - 22h30</span>
          </div>
        </div>
        <div class="schedule-footer">
          <i class="pi pi-info-circle"></i>
          <span>Ouvert à tous niveaux, ambiance conviviale</span>
        </div>
      </section>

      <section class="pp-block">
        <h2 class="pp-block__title"><i class="pi pi-map-marker"></i> Notre gymnase</h2>
        <h3 class="location-name">Gymnase Joël Abati</h3>
        <p class="location-address">
          <i class="pi pi-map"></i>
          1 Rue du Paraguay, 34830 Clapiers
        </p>
        <div class="location-features">
          <div class="location-feature"><i class="pi pi-check-circle"></i> <span>Parking gratuit</span></div>
          <div class="location-feature"><i class="pi pi-check-circle"></i> <span>Vestiaires disponibles</span></div>
        </div>
        <a
          href="https://maps.google.com/?q=Gymnase+Joel+Abati+Clapiers+34830"
          target="_blank"
          rel="noopener"
          class="location-link"
        >
          <i class="pi pi-external-link"></i>
          Ouvrir dans Google Maps
        </a>
      </section>
    </div>
  </PublicPage>
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

const { season } = useCurrentSeason()
</script>

<style scoped>
.season-notice {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  margin-bottom: 1.5rem;
  padding: 0.85rem 1.5rem;
  background: rgba(255, 255, 255, 0.12);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 10px;
  color: #fff;
  font-size: 0.95rem;
}

.schedule-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: var(--club-light);
  border-radius: 10px;
  margin-bottom: 0.75rem;
}

.day-badge {
  width: 48px;
  height: 48px;
  background: var(--club-primary);
  color: #fff;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.85rem;
  flex-shrink: 0;
}

.schedule-details {
  display: flex;
  flex-direction: column;
}
.schedule-day {
  font-weight: 600;
  color: var(--club-dark);
}
.schedule-time {
  color: #666;
  font-size: 0.9rem;
  margin-top: 0.15rem;
}

.schedule-footer {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-top: 0.75rem;
  padding-top: 1rem;
  border-top: 1px dashed rgba(0, 0, 0, 0.1);
  font-size: 0.85rem;
  color: #888;
}
.schedule-footer i { color: var(--club-secondary); }

.location-name {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--club-dark);
  margin: 0 0 0.5rem;
}
.location-address {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #666;
  margin: 0 0 1.25rem;
  font-size: 0.95rem;
}
.location-address i { color: var(--club-secondary); }

.location-features {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
}
.location-feature {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
  color: #555;
}
.location-feature i { color: #22c55e; }

.location-link {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.7rem 1.25rem;
  background: var(--club-secondary);
  color: #fff;
  text-decoration: none;
  font-weight: 500;
  font-size: 0.9rem;
  border-radius: 8px;
  transition: all 0.2s ease;
}
.location-link:hover {
  background: #c5303c;
  transform: translateY(-2px);
}
</style>
