<template>
    <div class="calendar-page">

        <CalendarToolbar
            :title="currentTitle"
            :active-view="activeView"
            :readonly="readonly"
            :teams="teams"
            v-model:selected-team-id="selectedTeamId"
            @prev="calendarApi?.prev()"
            @next="calendarApi?.next()"
            @today="calendarApi?.today()"
            @switch-view="switchView"
            @team-change="calendarApi?.refetchEvents()"
            @open-create="openCreateDialog(null)"
            @open-import="csvImportVisible = true"
        />

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
            <CsvImportDialog
                v-if="isSuperAdmin"
                v-model:visible="csvImportVisible"
                @imported="calendarApi?.refetchEvents()"
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
import multiMonthPlugin from '@fullcalendar/multimonth';
import interactionPlugin from '@fullcalendar/interaction';
import type { CalendarOptions, EventClickArg, DateSelectArg } from '@fullcalendar/core';
import frLocale from '@fullcalendar/core/locales/fr';
import type { Game } from '~/types/entity/Game';
import type { Team } from '~/types/entity/Team';
import { GameVenue } from '~/types/enum/GameVenue';
import GameFormDialog from '~/components/calendar/GameFormDialog.vue';
import GameDetailDialog from '~/components/calendar/GameDetailDialog.vue';
import CalendarToolbar from '~/components/calendar/CalendarToolbar.vue';
import CsvImportDialog from '~/components/calendar/CsvImportDialog.vue';
import type { CalendarViewType } from '~/components/calendar/CalendarToolbar.vue';
import { useCalendarEvents } from '~/composables/useCalendarEvents';

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

// ── Refs ──────────────────────────────────────────────

const calendarRef = ref<InstanceType<typeof FullCalendar> | null>(null);
const calendarWrapperRef = ref<HTMLElement | null>(null);
const calendarApi = computed(() => calendarRef.value?.getApi());
const currentTitle = ref('');
const activeView = ref<CalendarViewType>('dayGridMonth');
const selectedTeamId = ref<number | null>(null);

// ── Events composable ─────────────────────────────────

const {
    homeCountByDate,
    teamDateMap,
    eventSourceFn,
    recomputeCounts,
    handleGameSaved,
    handleGameDeleted,
    handleEventDrop,
} = useCalendarEvents(
    calendarApi,
    props.fetchFn,
    selectedTeamId,
    computed(() => props.readonly),
    isSuperAdmin,
);

// ── Dialogs ───────────────────────────────────────────

const csvImportVisible = ref(false);

const formDialog = reactive<{ visible: boolean; game: Game | null; initialDate: Date | null }>({
    visible: false, game: null, initialDate: null,
});
const detailDialog = reactive<{ visible: boolean; game: Game | null }>({
    visible: false, game: null,
});

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

// ── Helpers ───────────────────────────────────────────

const cellDateKey = (date: Date): string => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

const isMobile = () => typeof window !== 'undefined' && window.innerWidth < 768;

const switchView = (view: CalendarViewType) => {
    activeView.value = view;
    calendarApi.value?.changeView(view);
};

// ── Calendar options ──────────────────────────────────

const calendarOptions: CalendarOptions = {
    plugins: [dayGridPlugin, listPlugin, multiMonthPlugin, interactionPlugin],
    initialView: isMobile() ? 'listMonth' : 'dayGridMonth',
    locale: frLocale,
    headerToolbar: false,
    editable: !props.readonly,
    selectable: !props.readonly,
    selectMirror: !props.readonly,
    dayCellClassNames: (arg) => {
        const day = arg.date.getDay();
        return (day === 1 || day === 5) ? ['fc-home-day'] : [];
    },
    dayMaxEvents: true,
    weekends: true,
    height: '100%',
    events: eventSourceFn,
    eventsSet: () => recomputeCounts(calendarApi.value),
    eventContent: (arg) => {
        const game: Game = arg.event.extendedProps.game;
        const venue: string = game?.venue ?? '';
        const emoji = venue === GameVenue.HOME ? '🏠' : '✈️';
        const teamName = game?.team?.name ?? '';
        const opponent = game?.opponent ?? '';
        return { html: `<div class="fc-event-inner"><div class="fc-event-main"><span class="fc-event-team">${teamName} vs.</span><span class="fc-event-opponent">${opponent}</span></div><span class="fc-event-venue">${emoji}</span></div>` };
    },
    windowResize: () => {
        if (isMobile()) {
            if (calendarApi.value?.view.type !== 'listMonth') calendarApi.value?.changeView('listMonth');
        } else {
            if (calendarApi.value?.view.type === 'listMonth') calendarApi.value?.changeView(activeView.value);
        }
    },
    noEventsContent: 'Aucun match',
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
    border-radius: 4px;
    border: none !important;
    padding: 0 3px;
    font-size: 0.65rem;
    font-weight: 500;
    cursor: pointer;
}

:deep(.fc-event-inner) {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 3px;
    width: 100%;
    overflow: hidden;
}

:deep(.fc-event-main) {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    flex: 1;
    min-width: 0;
}

:deep(.fc-event-team) {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-weight: 600;
    font-size: 0.65rem;
    line-height: 1.15;
}

:deep(.fc-event-opponent) {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.55rem;
    font-weight: 400;
    opacity: 0.75;
    line-height: 1.15;
}

:deep(.fc-event-venue) { font-size: 0.65rem; flex-shrink: 0; line-height: 1; }
:deep(.fc-event:hover) { filter: brightness(0.9); }

:deep(.fc-daygrid-event) { margin-top: 1px; }
:deep(.fc-daygrid-day-events) { margin-top: 1px; }
:deep(.fc-daygrid-day-bottom) { font-size: 0.6rem; padding: 1px 3px; }

@media (max-width: 1366px) {
    :deep(.fc-event) { padding: 0 2px; font-size: 0.6rem; }
    :deep(.fc-event-team) { font-size: 0.6rem; line-height: 1.1; }
    :deep(.fc-event-opponent) { font-size: 0.52rem; line-height: 1.1; }
    :deep(.fc-event-venue) { font-size: 0.6rem; }
    :deep(.fc-col-header-cell-cushion) { font-size: 0.75rem; padding: 4px; }
    :deep(.fc-daygrid-day-number) { font-size: 0.75rem; padding: 2px 4px; }
}

:deep(.fc-col-header-cell-cushion),
:deep(.fc-daygrid-day-number) {
    color: var(--p-text-color);
    text-decoration: none;
}

:deep(.fc-daygrid-day.fc-day-today) {
    background: color-mix(in srgb, var(--p-primary-color) 8%, transparent);
}

:deep(.fc-daygrid-day.fc-home-day) {
    background: color-mix(in srgb, #f59e0b 12%, transparent);
}

:deep(.fc-daygrid-day.fc-home-day.fc-day-today) {
    background: color-mix(in srgb, #f59e0b 18%, transparent);
}

:deep(.fc-col-header-cell:nth-child(1) .fc-col-header-cell-cushion),
:deep(.fc-col-header-cell:nth-child(5) .fc-col-header-cell-cushion) {
    color: #f59e0b;
    font-weight: 700;
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
    font-size: 0.55rem;
    font-weight: 600;
    background: color-mix(in srgb, var(--p-primary-color) 15%, transparent);
    color: var(--p-primary-color);
    border-radius: 3px;
    padding: 0 3px;
    white-space: nowrap;
    line-height: 1.3;
}

:deep(.fc-home-badge--full) {
    background: color-mix(in srgb, var(--p-red-500, #ef4444) 15%, transparent);
    color: var(--p-red-500, #ef4444);
}

@media (max-width: 768px) {
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
