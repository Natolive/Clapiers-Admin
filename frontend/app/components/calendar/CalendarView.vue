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
                    v-if="!readonly && teams.length > 1"
                    class="cal-team-select"
                    v-model="selectedTeamId"
                    :options="[{ id: null, name: 'Toutes les équipes' }, ...teams]"
                    option-label="name"
                    option-value="id"
                    placeholder="Toutes les équipes"
                    size="small"
                    style="min-width: 12rem"
                    @change="loadGames"
                />
                <template v-if="!readonly">
                    <Button class="cal-add-btn" label="Nouveau match" icon="pi pi-plus" size="small" @click="openCreateDialog(null)" />
                    <Button class="cal-add-btn--icon" icon="pi pi-plus" rounded size="small" @click="openCreateDialog(null)" />
                </template>
            </div>
        </div>

        <!-- Calendar -->
        <div class="cal-wrapper" ref="calendarWrapperRef">
            <FullCalendar ref="calendarRef" :options="calendarOptions">
                <template #dayCellContent="arg">
                    <div class="fc-cell-top">
                        <span
                            v-if="homeCountByDate[cellDateKey(arg.date)]"
                            class="fc-home-badge"
                            :class="{ 'fc-home-badge--full': homeCountByDate[cellDateKey(arg.date)] >= 3 }"
                        >
                            🏠 {{ homeCountByDate[cellDateKey(arg.date)] }}/3
                        </span>
                        <span class="fc-daygrid-day-number">{{ arg.dayNumberText }}</span>
                    </div>
                </template>
            </FullCalendar>
        </div>

        <!-- Dialogs (admin only) -->
        <template v-if="!readonly">
            <GameFormDialog
                v-model:visible="formDialog.visible"
                :game="formDialog.game"
                :initial-date="formDialog.initialDate"
                :teams="teams"
                :user-team-id="userTeamId"
                :home-count-by-date="homeCountByDate"
                :team-date-map="teamDateMap"
                @saved="handleGameSaved"
            />
        </template>

        <GameDetailDialog
            v-model:visible="detailDialog.visible"
            :game="detailDialog.game"
            @edit="handleEditGame"
            @deleted="handleGameDeleted"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import type { CalendarOptions, EventClickArg, DateSelectArg, EventDropArg, EventInput } from '@fullcalendar/core';
import frLocale from '@fullcalendar/core/locales/fr';
import type { Game } from '~/types/entity/Game';
import type { Team } from '~/types/entity/Team';
import { GameVenue } from '~/types/enum/GameVenue';
import { getTeamColor } from '~/utils/teamColors';
import GameFormDialog from '~/components/calendar/GameFormDialog.vue';
import GameDetailDialog from '~/components/calendar/GameDetailDialog.vue';
import { GameRepository } from '~/repository/game-repository';

type FetchFn = (params: { start: string; end: string; teamId?: number | null }) => Promise<Game[]>;

const props = withDefaults(defineProps<{
    readonly?: boolean;
    fetchFn: FetchFn;
    teams?: Team[];
    userTeamId?: number | null;
}>(), {
    readonly: false,
    teams: () => [],
    userTeamId: null,
});

const { isSuperAdmin } = useUserRole();
const toast = usePVToastService();
const gameRepository = new GameRepository();

const calendarRef = ref<InstanceType<typeof FullCalendar> | null>(null);
const calendarWrapperRef = ref<HTMLElement | null>(null);
const calendarApi = computed(() => calendarRef.value?.getApi());
const currentTitle = ref('');
const selectedTeamId = ref<number | null>(null);
const currentDateRange = ref<{ start: string; end: string } | null>(null);
const homeCountByDate = ref<Record<string, number>>({});
const teamDateMap = ref<Record<string, number>>({});

const formDialog = reactive<{ visible: boolean; game: Game | null; initialDate: Date | null }>({
    visible: false, game: null, initialDate: null,
});
const detailDialog = reactive<{ visible: boolean; game: Game | null }>({
    visible: false, game: null,
});

// ── Helpers ──────────────────────────────────────────

const cellDateKey = (date: Date): string => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

const toDateString = (d: Date): string => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
};

const gameToEvent = (game: Game): EventInput => ({
    id: String(game.id),
    title: `vs ${game.opponent}`,
    start: game.date,
    allDay: true,
    backgroundColor: getTeamColor(game.team.id),
    borderColor: getTeamColor(game.team.id),
    extendedProps: { game },
});

const recomputeCounts = (api: ReturnType<typeof calendarApi.value>) => {
    const counts: Record<string, number> = {};
    const tdMap: Record<string, number> = {};
    api?.getEvents().forEach(e => {
        const g: Game | undefined = e.extendedProps.game;
        if (!g) return;
        if (g.venue === GameVenue.HOME) counts[g.date] = (counts[g.date] ?? 0) + 1;
        tdMap[`${g.date}|${g.team.id}`] = g.id;
    });
    homeCountByDate.value = counts;
    teamDateMap.value = tdMap;
};

// ── Fetch ────────────────────────────────────────────

const loadGames = async () => {
    if (!currentDateRange.value) return;
    try {
        const games = await props.fetchFn({
            start: currentDateRange.value.start,
            end: currentDateRange.value.end,
            teamId: selectedTeamId.value,
        });

        const counts: Record<string, number> = {};
        const tdMap: Record<string, number> = {};
        games.forEach(g => {
            if (g.venue === GameVenue.HOME) counts[g.date] = (counts[g.date] ?? 0) + 1;
            tdMap[`${g.date}|${g.team.id}`] = g.id;
        });
        homeCountByDate.value = counts;
        teamDateMap.value = tdMap;

        const api = calendarApi.value;
        if (!api) return;
        api.removeAllEvents();
        games.forEach(g => api.addEvent(gameToEvent(g)));
    } catch {
        if (!props.readonly) {
            toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les matchs', life: 4000 });
        }
    }
};

// ── Dialog actions ────────────────────────────────────

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
    recomputeCounts(api);
};

const handleGameDeleted = (id: number) => {
    const api = calendarApi.value;
    if (!api) return;
    api.getEventById(String(id))?.remove();
    recomputeCounts(api);
};

// ── Drag & drop ──────────────────────────────────────

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
        recomputeCounts(calendarApi.value);
        toast.add({ severity: 'success', summary: 'Match déplacé', life: 2000 });
    } catch {
        info.revert();
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de déplacer le match', life: 4000 });
    }
};

// ── Calendar options ──────────────────────────────────

const isMobile = () => typeof window !== 'undefined' && window.innerWidth < 768;

const calendarOptions: CalendarOptions = {
    plugins: [dayGridPlugin, listPlugin, interactionPlugin],
    initialView: isMobile() ? 'listMonth' : 'dayGridMonth',
    locale: frLocale,
    headerToolbar: false,
    editable: !props.readonly,
    selectable: !props.readonly,
    selectMirror: !props.readonly,
    dayMaxEvents: true,
    weekends: true,
    height: '100%',
    events: [],
    eventContent: (arg) => {
        const venue: string = arg.event.extendedProps.game?.venue ?? '';
        const emoji = venue === GameVenue.HOME ? '🏠' : '✈️';
        return { html: `<div class="fc-event-inner"><span class="fc-event-title">${arg.event.title}</span><span class="fc-event-venue">${emoji}</span></div>` };
    },
    windowResize: () => {
        const view = isMobile() ? 'listMonth' : 'dayGridMonth';
        if (calendarApi.value?.view.type !== view) calendarApi.value?.changeView(view);
    },
    noEventsContent: 'Aucun match ce mois-ci',
    eventClick: (info: EventClickArg) => {
        detailDialog.game = info.event.extendedProps.game as Game;
        detailDialog.visible = true;
    },
    select: props.readonly ? undefined : (info: DateSelectArg) => {
        openCreateDialog(info.start);
        calendarApi.value?.unselect();
    },
    eventDrop: props.readonly ? undefined : handleEventDrop,
    datesSet: (info) => {
        currentTitle.value = info.view.title;
        currentDateRange.value = { start: info.startStr, end: info.endStr };
        loadGames();
    },
};

// ── Lifecycle ─────────────────────────────────────────

let resizeObserver: ResizeObserver | null = null;

onMounted(() => {
    if (calendarWrapperRef.value) {
        resizeObserver = new ResizeObserver(() => calendarApi.value?.updateSize());
        resizeObserver.observe(calendarWrapperRef.value);
    }
});

onUnmounted(() => resizeObserver?.disconnect());
</script>

<style scoped>
.calendar-page {
    display: flex;
    flex-direction: column;
    height: 100%;
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

:deep(.fc) { height: 100%; font-family: inherit; }
:deep(.fc-daygrid-day:hover) { background: var(--p-surface-hover); cursor: pointer; }

:deep(.fc-event) {
    border-radius: 6px;
    border: none !important;
    padding: 1px 5px;
    font-size: 0.8125rem;
    font-weight: 500;
    cursor: pointer;
}

:deep(.fc-event-inner) {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 4px;
    width: 100%;
    overflow: hidden;
}

:deep(.fc-event-title) {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 1;
}

:deep(.fc-event-venue) { font-size: 0.7rem; flex-shrink: 0; }
:deep(.fc-event:hover) { filter: brightness(0.9); }

:deep(.fc-col-header-cell-cushion),
:deep(.fc-daygrid-day-number) {
    color: var(--p-text-color);
    text-decoration: none;
}

:deep(.fc-daygrid-day.fc-day-today) {
    background: color-mix(in srgb, var(--p-primary-color) 8%, transparent);
}

:deep(.fc-cell-top) {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    gap: 0.25rem;
    padding: 2px 2px 0;
}

:deep(.fc-home-badge) {
    font-size: 0.6rem;
    font-weight: 600;
    background: color-mix(in srgb, var(--p-primary-color) 15%, transparent);
    color: var(--p-primary-color);
    border-radius: 4px;
    padding: 1px 4px;
    white-space: nowrap;
    line-height: 1.4;
}

:deep(.fc-home-badge--full) {
    background: color-mix(in srgb, var(--p-red-500, #ef4444) 15%, transparent);
    color: var(--p-red-500, #ef4444);
}

.cal-add-btn--icon { display: none; }

@media (max-width: 768px) {
    .cal-toolbar { gap: 0.5rem; }
    .cal-toolbar__title { font-size: 0.95rem; }
    .cal-team-select { display: none; }
    .cal-add-btn { display: none; }
    .cal-add-btn--icon { display: inline-flex; }
    .cal-wrapper { padding: 0.25rem 0; border: none; border-radius: 8px; box-shadow: none; }

    :deep(.fc-list) { border: none; }
    :deep(.fc-list-day-cushion) {
        background: var(--p-surface-ground);
        padding: 0.5rem 1rem;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: capitalize;
        color: var(--p-text-muted-color);
        letter-spacing: 0.04em;
    }
    :deep(.fc-list-event) { cursor: pointer; }
    :deep(.fc-list-event-dot) { border-width: 6px; }
    :deep(.fc-list-event-title) { font-size: 0.875rem; font-weight: 500; padding: 0.625rem 1rem; }
    :deep(.fc-list-event:hover td) { background: var(--p-surface-hover); }
    :deep(.fc-list-empty) { background: transparent; color: var(--p-text-muted-color); font-size: 0.875rem; }
    :deep(.fc-theme-standard td),
    :deep(.fc-theme-standard th),
    :deep(.fc-theme-standard .fc-list) { border: none; }
}
</style>
