<template>
  <div class="cal-full">
    <div class="cal-full__head">
      <h1 class="cal-full__title">Calendrier des matchs</h1>
      <CalendarSubscribe :teams="teams" class="cal-full__subscribe" />
      <div class="cal-full__switch">
        <button type="button" :class="{ on: view === 'season' }" @click="view = 'season'">Saison</button>
        <button type="button" :class="{ on: view === 'grid' }" @click="view = 'grid'">Grille</button>
      </div>
    </div>
    <div class="cal-full__card">
      <SeasonOverview v-if="view === 'season'" :games="upcoming" :teams="teams" :closures="closures" :loading="loadingUpcoming" />
      <ClientOnly v-else>
        <CalendarView
          :readonly="true"
          :fetch-fn="fetchFn"
          :closure-fetch-fn="closureFetchFn"
          :teams="teams"
        />
      </ClientOnly>
    </div>
  </div>
</template>

<script setup lang="ts">
import CalendarView from '~/components/calendar/CalendarView.vue';
import SeasonOverview from '~/components/calendar/SeasonOverview.vue';
import CalendarSubscribe from '~/components/calendar/CalendarSubscribe.vue';
import type { CalendarFetchFn } from '~/composables/useCalendarEvents';
import type { SalleClosureFetchFn } from '~/composables/useSalleClosures';
import type { Game } from '~/types/entity/Game';
import type { SalleClosure } from '~/types/entity/SalleClosure';
import type { Team } from '~/types/entity/Team';
import { toDateKey } from '~/utils/calendarRules';

definePageMeta({ layout: 'public' });
useSeoMeta({
  title: 'Calendrier des matchs - Clapiers Volley Ball',
  description: 'Consultez le calendrier des matchs du Clapiers Volley Ball. Retrouvez toutes les dates et horaires des rencontres de nos équipes.',
  ogTitle: 'Calendrier des matchs - Clapiers Volley Ball',
  ogDescription: 'Consultez le calendrier des matchs du Clapiers Volley Ball. Retrouvez toutes les dates et horaires des rencontres de nos équipes.',
  ogUrl: 'https://clapiersvb.fr/calendrier',
  twitterTitle: 'Calendrier des matchs - Clapiers Volley Ball',
  twitterDescription: 'Consultez le calendrier des matchs du Clapiers Volley Ball. Toutes les dates et horaires des rencontres.',
});
useHead({
  link: [{ rel: 'canonical', href: 'https://clapiersvb.fr/calendrier' }],
});

const config = useRuntimeConfig();

const fetchFn: CalendarFetchFn = ({ start, end }) =>
    $fetch<Game[]>(`${config.public.apiBase}/public/games`, {
        params: { start, end },
    });

const closureFetchFn: SalleClosureFetchFn = () =>
    $fetch<SalleClosure[]>(`${config.public.apiBase}/public/closures`);

const teams = ref<Team[]>([]);
const closures = ref<SalleClosure[]>([]);
const view = ref<'season' | 'grid'>('season');

// Vue saison : matchs à venir sur un an (plafond de l'endpoint public)
const today = toDateKey(new Date());
const { data: upcoming, status } = useLazyAsyncData('public-upcoming-games', () =>
    fetchFn({ start: today, end: `${Number(today.slice(0, 4)) + 1}${today.slice(4)}` }),
    { server: false, default: () => [] as Game[] },
);
// 'idle' côté serveur (server: false) : même rendu qu'au chargement client, pas de mismatch
const loadingUpcoming = computed(() => status.value === 'idle' || status.value === 'pending');

onMounted(async () => {
    [teams.value, closures.value] = await Promise.all([
        $fetch<Team[]>(`${config.public.apiBase}/public/teams`).catch(() => []),
        closureFetchFn().catch(() => []),
    ]);
});
</script>

<style scoped>
/* Pleine largeur, au moins un écran de haut (la navbar fixe est dégagée par le
   padding). La carte grandit avec son contenu : c'est la page qui scrolle. */
.cal-full {
  min-height: 100dvh;
  padding: 5.5rem 1.5rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  background: var(--club-gradient);
}

.cal-full__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.cal-full__subscribe {
  margin-left: auto;
}

.cal-full__switch {
  display: flex;
  padding: 0.2rem;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.15);
}
.cal-full__switch button {
  padding: 0.3rem 0.9rem;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: #fff;
  font: inherit;
  font-size: 0.9rem;
  cursor: pointer;
}
.cal-full__switch button.on {
  background: #fff;
  color: var(--club-primary);
  font-weight: 600;
}

.cal-full__title {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 700;
  color: #fff;
}

.cal-full__card {
  flex: 1;
  padding: 1rem;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
}
/* La carte sert de cadre : celui de CalendarView ferait une carte dans la carte */
.cal-full__card :deep(.cal-wrapper) {
  padding: 0;
  border: 0;
  border-radius: 0;
  overflow: visible;
}

@media (max-width: 768px) {
  .cal-full {
    padding: 5rem 0.5rem 0.5rem;
  }

  .cal-full__card {
    padding: 0.5rem;
  }
}
</style>
