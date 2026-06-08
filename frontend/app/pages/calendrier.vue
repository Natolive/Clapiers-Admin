<template>
    <div class="public-calendar-page">
        <div class="public-calendar-header">
            <h1 class="public-calendar-title">Calendrier des matchs</h1>
            <p class="public-calendar-sub">Retrouvez tous les matchs de nos équipes</p>
        </div>
        <div class="public-calendar-wrapper">
            <ClientOnly>
                <CalendarView :readonly="true" :fetch-fn="fetchFn" :closure-fetch-fn="closureFetchFn" />
            </ClientOnly>
        </div>
    </div>
</template>

<script setup lang="ts">
import CalendarView from '~/components/calendar/CalendarView.vue';
import type { CalendarFetchFn } from '~/composables/useCalendarEvents';
import type { SalleClosureFetchFn } from '~/composables/useSalleClosures';
import type { Game } from '~/types/entity/Game';
import type { SalleClosure } from '~/types/entity/SalleClosure';

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
</script>

<style scoped>
.public-calendar-page {
    min-height: calc(100vh - 4rem);
    padding: 6rem 2rem 2rem;
    background: var(--club-light, #f8fafc);
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.public-calendar-header {
    text-align: center;
}

.public-calendar-title {
    font-size: 2rem;
    font-weight: 800;
    color: var(--club-dark, #0f1e35);
    margin: 0 0 0.5rem;
}

.public-calendar-sub {
    color: var(--club-gray, #64748b);
    font-size: 1rem;
    margin: 0;
}

.public-calendar-wrapper {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 2px 20px rgba(0, 0, 0, 0.06);
    /* navbar ~4rem + page padding 6rem + header ~3rem + gap 1rem */
    height: calc(100vh - 8rem);
    min-height: 640px;
}

@media (max-width: 768px) {
    .public-calendar-page {
        padding: 5rem 0.75rem 2rem;
    }
    .public-calendar-title {
        font-size: 1.5rem;
    }
    .public-calendar-wrapper {
        padding: 0.75rem;
        height: calc(100vh - 8rem);
    }
}
</style>
