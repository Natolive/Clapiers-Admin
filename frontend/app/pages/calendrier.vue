<template>
  <PublicPage
    label="Compétitions"
    title="Calendrier des matchs"
    subtitle="Retrouvez toutes les dates et horaires des rencontres de nos équipes."
    wide
  >
    <section class="pp-block calendar-block">
      <ClientOnly>
        <CalendarView
          :readonly="true"
          :fetch-fn="fetchFn"
          :closure-fetch-fn="closureFetchFn"
          :teams="teams"
        />
      </ClientOnly>
    </section>
  </PublicPage>
</template>

<script setup lang="ts">
import CalendarView from '~/components/calendar/CalendarView.vue';
import type { CalendarFetchFn } from '~/composables/useCalendarEvents';
import type { SalleClosureFetchFn } from '~/composables/useSalleClosures';
import type { Game } from '~/types/entity/Game';
import type { SalleClosure } from '~/types/entity/SalleClosure';
import type { Team } from '~/types/entity/Team';

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

onMounted(async () => {
    teams.value = await $fetch<Team[]>(`${config.public.apiBase}/public/teams`).catch(() => []);
});
</script>

<style scoped>
.calendar-block {
  padding: 1.5rem;
  height: calc(100vh - 14rem);
  min-height: 640px;
}

@media (max-width: 768px) {
  .calendar-block {
    padding: 0.75rem;
  }
}
</style>
