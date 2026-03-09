<template>
    <div class="calendar-page">

        <!-- Toolbar -->
        <div class="cal-toolbar">
            <div class="cal-toolbar__nav">
                <Button icon="pi pi-chevron-left" text rounded @click="calendarApi?.prev()" />
                <Button icon="pi pi-chevron-right" text rounded @click="calendarApi?.next()" />
                <Button label="Aujourd'hui" outlined size="small" @click="calendarApi?.today()" />
            </div>

            <h2 class="cal-toolbar__title capitalize">{{ currentTitle }}</h2>

            <div class="cal-toolbar__actions">
                <Select
                    v-model="selectedTeamId"
                    :options="[{ id: null, name: 'Toutes les équipes' }, ...teams]"
                    option-label="name"
                    option-value="id"
                    placeholder="Toutes les équipes"
                    size="small"
                    style="min-width: 12rem"
                    @change="fetchGames"
                />
                <Button label="Nouveau match" icon="pi pi-plus" size="small" @click="openCreateDialog(null)" />
            </div>
        </div>

        <!-- Calendar -->
        <div class="cal-wrapper">
            <FullCalendar ref="calendarRef" :options="calendarOptions" />
        </div>

        <!-- Dialogs -->
        <GameFormDialog
            v-model:visible="formDialog.visible"
            :game="formDialog.game"
            :initial-date="formDialog.initialDate"
            :teams="teams"
            :user-team-id="userTeamId"
            @saved="handleGameSaved"
        />
        <GameDetailDialog
            v-model:visible="detailDialog.visible"
            :game="detailDialog.game"
            @edit="handleEditGame"
            @deleted="handleGameDeleted"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import type { CalendarOptions, EventClickArg, DateSelectArg, EventDropArg, EventInput } from '@fullcalendar/core';
import frLocale from '@fullcalendar/core/locales/fr';
import { GameRepository } from '~/repository/game-repository';
import { TeamRepository } from '~/repository/team-repository';
import { useAuthStore } from '~/stores/auth.store';
import type { Game } from '~/types/entity/Game';
import type { Team } from '~/types/entity/Team';
import { getTeamColor } from '~/utils/teamColors';
import GameFormDialog from '~/components/calendar/GameFormDialog.vue';
import GameDetailDialog from '~/components/calendar/GameDetailDialog.vue';

definePageMeta({ middleware: 'auth-middleware', layout: 'dashboard' });
useHead({ title: 'Calendrier' });

const { isSuperAdmin } = useUserRole();
const toast = usePVToastService();
const authStore = useAuthStore();
const gameRepository = new GameRepository();
const teamRepository = new TeamRepository();

const calendarRef = ref<InstanceType<typeof FullCalendar> | null>(null);
const calendarApi = computed(() => calendarRef.value?.getApi());
const currentTitle = ref('');
const teams = ref<Team[]>([]);
const selectedTeamId = ref<number | null>(null);
const currentDateRange = ref<{ start: string; end: string } | null>(null);

// Current user's team (if linked to a member)
const userTeamId = computed(() => authStore.user?.member?.team?.id ?? null);

const formDialog = reactive<{ visible: boolean; game: Game | null; initialDate: Date | null }>({
    visible: false, game: null, initialDate: null,
});
const detailDialog = reactive<{ visible: boolean; game: Game | null }>({
    visible: false, game: null,
});

// ── Helpers ─────────────────────────────────────────

const gameToEvent = (game: Game): EventInput => ({
    id: String(game.id),
    title: `vs ${game.opponent}`,
    start: game.date,
    allDay: true,
    backgroundColor: getTeamColor(game.team.id),
    borderColor: getTeamColor(game.team.id),
    extendedProps: { game },
});

const fetchGames = async () => {
    if (!currentDateRange.value) return;
    try {
        const games = await gameRepository.getAll({
            teamId: selectedTeamId.value,
            start: currentDateRange.value.start,
            end: currentDateRange.value.end,
        });
        const api = calendarApi.value;
        if (!api) return;
        api.removeAllEvents();
        games.forEach(g => api.addEvent(gameToEvent(g)));
    } catch {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les matchs', life: 4000 });
    }
};

// ── Dialog actions ───────────────────────────────────

const openCreateDialog = (date: Date | null) => {
    formDialog.game = null;
    formDialog.initialDate = date;
    formDialog.visible = true;
};

const handleEditGame = (game: Game) => {
    detailDialog.visible = false;
    formDialog.game = game;
    formDialog.initialDate = null;
    formDialog.visible = true;
};

const handleGameSaved = (game: Game) => {
    const api = calendarApi.value;
    if (!api) return;
    api.getEventById(String(game.id))?.remove();
    api.addEvent(gameToEvent(game));
};

const handleGameDeleted = (id: number) => {
    calendarApi.value?.getEventById(String(id))?.remove();
};

// ── Drag & drop (date only) ──────────────────────────

const toDateString = (d: Date): string => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
};

const handleEventDrop = async (info: EventDropArg) => {
    const game: Game = info.event.extendedProps.game;
    try {
        const updated = await gameRepository.update(game.id, {
            opponent:    game.opponent,
            date:        toDateString(info.event.start as Date),
            meetingTime: game.meetingTime,
            venue:       game.venue,
            location:    game.location,
            teamId:      isSuperAdmin.value ? game.team.id : undefined,
        });
        info.event.setExtendedProp('game', updated);
        toast.add({ severity: 'success', summary: 'Match déplacé', life: 2000 });
    } catch {
        info.revert();
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de déplacer le match', life: 4000 });
    }
};

// ── Calendar options ─────────────────────────────────

const calendarOptions: CalendarOptions = {
    plugins: [dayGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    locale: frLocale,
    headerToolbar: false,
    editable: true,
    selectable: true,
    selectMirror: true,
    dayMaxEvents: true,
    weekends: true,
    height: '100%',
    events: [],
    eventClick: (info: EventClickArg) => {
        detailDialog.game = info.event.extendedProps.game as Game;
        detailDialog.visible = true;
    },
    select: (info: DateSelectArg) => {
        openCreateDialog(info.start);
        calendarApi.value?.unselect();
    },
    eventDrop: handleEventDrop,
    datesSet: (info) => {
        currentTitle.value = info.view.title;
        currentDateRange.value = { start: info.startStr, end: info.endStr };
        fetchGames();
    },
};

onMounted(async () => {
    if (isSuperAdmin.value) {
        teams.value = await teamRepository.getAll().catch(() => []);
    } else {
        const team = authStore.user?.member?.team;
        teams.value = team ? [team] : [];
    }
});
</script>

<style scoped>
.calendar-page {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 3.75rem - 3rem);
    gap: 1rem;
}

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

.cal-wrapper {
    flex: 1;
    min-height: 0;
    background: var(--p-surface-card);
    border-radius: 12px;
    border: 1px solid var(--p-surface-border);
    padding: 1rem;
    overflow: hidden;
}

:deep(.fc) {
    height: 100%;
    font-family: inherit;
}

:deep(.fc-daygrid-day:hover) {
    background: var(--p-surface-hover);
    cursor: pointer;
}

:deep(.fc-event) {
    border-radius: 6px;
    border: none !important;
    padding: 1px 5px;
    font-size: 0.8125rem;
    font-weight: 500;
    cursor: pointer;
}

:deep(.fc-event:hover) {
    filter: brightness(0.9);
}

:deep(.fc-col-header-cell-cushion),
:deep(.fc-daygrid-day-number) {
    color: var(--p-text-color);
    text-decoration: none;
}

:deep(.fc-daygrid-day.fc-day-today) {
    background: color-mix(in srgb, var(--p-primary-color) 8%, transparent);
}

@media (max-width: 768px) {
    .cal-toolbar__title {
        order: -1;
        width: 100%;
        text-align: left;
        font-size: 1rem;
    }
    .cal-toolbar__actions { margin-left: auto; }
}
</style>
