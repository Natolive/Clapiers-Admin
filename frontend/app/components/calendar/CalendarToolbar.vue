<template>
    <div class="cal-toolbar">
        <div class="cal-toolbar__nav">
            <Button icon="pi pi-chevron-left" text rounded @click="emit('prev')" />
            <Button icon="pi pi-chevron-right" text rounded @click="emit('next')" />
            <Button label="Aujourd'hui" outlined size="small" @click="emit('today')" />
        </div>

        <h2 class="cal-toolbar__title capitalize">{{ title }}</h2>

        <div class="cal-view-switcher">
            <Button
                label="Mois"
                :outlined="activeView !== 'dayGridMonth'"
                :severity="activeView === 'dayGridMonth' ? undefined : 'secondary'"
                size="small"
                @click="emit('switchView', 'dayGridMonth')"
            />
            <Button
                label="Année"
                :outlined="activeView !== 'multiMonthYear'"
                :severity="activeView === 'multiMonthYear' ? undefined : 'secondary'"
                size="small"
                @click="emit('switchView', 'multiMonthYear')"
            />
            <Button
                label="Liste"
                :outlined="activeView !== 'listYear'"
                :severity="activeView === 'listYear' ? undefined : 'secondary'"
                size="small"
                @click="emit('switchView', 'listYear')"
            />
        </div>

        <div class="cal-toolbar__actions">
            <Select
                v-if="!readonly && teams.length > 1"
                class="cal-team-select"
                :model-value="selectedTeamId"
                :options="[{ id: null, name: 'Toutes les équipes' }, ...teams]"
                option-label="name"
                option-value="id"
                placeholder="Toutes les équipes"
                size="small"
                style="min-width: 12rem"
                @update:model-value="onTeamChange"
            />
            <template v-if="!readonly">
                <Button
                    v-if="isSuperAdmin"
                    class="cal-add-btn"
                    label="Importer CSV"
                    icon="pi pi-upload"
                    severity="secondary"
                    size="small"
                    @click="emit('openImport')"
                />
                <Button class="cal-add-btn" label="Nouveau match" icon="pi pi-plus" size="small" @click="emit('openCreate')" />
                <Button class="cal-add-btn--icon" icon="pi pi-plus" rounded size="small" @click="emit('openCreate')" />
            </template>
        </div>
    </div>
</template>

<script setup lang="ts">
import type { Team } from '~/types/entity/Team';

export type CalendarViewType = 'dayGridMonth' | 'multiMonthYear' | 'listYear';

const { isSuperAdmin } = useUserRole();

const props = defineProps<{
    title: string;
    activeView: CalendarViewType;
    readonly: boolean;
    teams: Team[];
    selectedTeamId: number | null;
}>();

const emit = defineEmits<{
    prev: [];
    next: [];
    today: [];
    switchView: [view: CalendarViewType];
    'update:selectedTeamId': [id: number | null];
    teamChange: [];
    openCreate: [];
    openImport: [];
}>();

// Met à jour le teamId dans le parent AVANT d'émettre teamChange
// pour que refetchEvents lise la nouvelle valeur
const onTeamChange = (value: number | null) => {
    emit('update:selectedTeamId', value);
    emit('teamChange');
};
</script>

<style scoped>
.cal-toolbar {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-shrink: 0;
    flex-wrap: wrap;
}

.cal-toolbar__nav {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.cal-toolbar__title {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
    color: var(--p-text-color);
    flex: 1;
    text-align: center;
}

.cal-toolbar__actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.cal-view-switcher {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.cal-add-btn--icon { display: none; }

@media (max-width: 768px) {
    .cal-view-switcher { display: none; }
    .cal-toolbar { gap: 0.5rem; }
    .cal-toolbar__title { font-size: 0.95rem; }
    .cal-team-select { display: none; }
    .cal-add-btn { display: none; }
    .cal-add-btn--icon { display: inline-flex; }
}
</style>
