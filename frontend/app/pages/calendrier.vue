<template>
    <div class="public-calendar-page">
        <div class="public-calendar-header">
            <h1 class="public-calendar-title">Calendrier des matchs</h1>
            <p class="public-calendar-sub">Retrouvez tous les matchs de nos équipes</p>
        </div>
        <div class="public-calendar-wrapper">
            <CalendarView :readonly="true" :fetch-fn="fetchFn" />
        </div>
    </div>
</template>

<script setup lang="ts">
import CalendarView from '~/components/calendar/CalendarView.vue';

definePageMeta({ layout: 'public' });
useHead({ title: 'Calendrier — VBC Clapiers' });

const config = useRuntimeConfig();

const fetchFn = ({ start, end }: { start: string; end: string }) =>
    $fetch<any[]>(`${config.public.apiBase}/public/games`, {
        params: { start, end },
    });
</script>

<style scoped>
.public-calendar-page {
    min-height: calc(100vh - 4rem);
    padding: 6rem 2rem 3rem;
    background: var(--club-light, #f8fafc);
    display: flex;
    flex-direction: column;
    gap: 2rem;
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
    /* navbar ~4rem + page padding 6rem + header ~6rem + gap 2rem */
    height: calc(100vh - 18rem);
    min-height: 500px;
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
        height: calc(100vh - 14rem);
    }
}
</style>
