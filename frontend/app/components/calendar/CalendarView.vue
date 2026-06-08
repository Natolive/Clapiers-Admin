<template>
    <div class="calendar-page">

        <CalendarToolbar
            :title="currentTitle"
            :active-view="activeView"
            :readonly="readonly"
            :is-super-admin="isSuperAdmin"
            :teams="teams"
            v-model:selected-team-ids="selectedTeamIds"
            @prev="calendarApi?.prev()"
            @next="calendarApi?.next()"
            @today="calendarApi?.today()"
            @switch-view="switchView"
            @team-change="calendarApi?.refetchEvents()"
            @open-create="openCreateDialog(null)"
            @open-import="csvImportVisible = true"
            @open-closures="closureDialogVisible = true"
        />

        <div class="cal-wrapper" ref="calendarWrapperRef">
            <FullCalendar ref="calendarRef" :options="calendarOptions">
                <template #dayCellContent="arg">
                    <div class="fc-cell-top">
                        <span class="fc-cell-badges">
                            <span
                                v-if="closureForDateKey(cellDateKey(arg.date))"
                                class="fc-closure-badge"
                                v-tooltip.top="closureForDateKey(cellDateKey(arg.date))?.reason || 'Salle fermée'"
                            >
                                <i class="pi pi-lock"></i>
                            </span>
                            <span
                                v-if="homeCountByDate[cellDateKey(arg.date)]"
                                class="fc-home-badge"
                                :class="{ 'fc-home-badge--full': (homeCountByDate[cellDateKey(arg.date)] ?? 0) >= MAX_HOME_GAMES_PER_DAY }"
                            >
                                🏠 {{ homeCountByDate[cellDateKey(arg.date)] }}/{{ MAX_HOME_GAMES_PER_DAY }}
                            </span>
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
                :closures="closures"
                @saved="handleGameSaved"
            />
            <CsvImportDialog
                v-if="isSuperAdmin"
                v-model:visible="csvImportVisible"
                @imported="calendarApi?.refetchEvents()"
            />
            <SalleClosureDialog
                v-if="isSuperAdmin"
                v-model:visible="closureDialogVisible"
                :closures="closures"
                @changed="handleClosuresChanged"
            />
        </template>

        <GameDetailDialog
            v-model:visible="detailDialog.visible"
            :game="detailDialog.game"
            :readonly="readonly"
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
import { MAX_HOME_GAMES_PER_DAY, isHomeDay, toDateKey } from '~/utils/calendarRules';
import GameFormDialog from '~/components/calendar/GameFormDialog.vue';
import GameDetailDialog from '~/components/calendar/GameDetailDialog.vue';
import CalendarToolbar from '~/components/calendar/CalendarToolbar.vue';
import CsvImportDialog from '~/components/calendar/CsvImportDialog.vue';
import SalleClosureDialog from '~/components/calendar/SalleClosureDialog.vue';
import type { CalendarViewType } from '~/components/calendar/CalendarToolbar.vue';
import { useCalendarEvents } from '~/composables/useCalendarEvents';
import type { CalendarFetchFn } from '~/composables/useCalendarEvents';
import { useSalleClosures } from '~/composables/useSalleClosures';
import type { SalleClosureFetchFn } from '~/composables/useSalleClosures';

const props = withDefaults(defineProps<{
    readonly?: boolean;
    fetchFn: CalendarFetchFn;
    closureFetchFn: SalleClosureFetchFn;
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
// Actual rendered view (mobile forces listMonth without touching activeView)
const currentViewType = ref<string>('dayGridMonth');
const selectedTeamIds = ref<number[]>([]);

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
    selectedTeamIds,
    computed(() => props.readonly),
    isSuperAdmin,
);

// ── Closures ──────────────────────────────────────────

const { closures, reload: reloadClosures, closureForDateKey } = useSalleClosures(props.closureFetchFn);

// FullCalendar allDay end is exclusive, so closed range end needs +1 day
const nextDayKey = (dateKey: string): string => {
    const d = new Date(dateKey + 'T00:00:00');
    d.setDate(d.getDate() + 1);
    return toDateKey(d);
};

// List view (mobile) ignores background events, so closures are rendered as
// real list entries there; month/year views keep the greyed background.
const isListView = computed(() => activeView.value.startsWith('list') || currentViewType.value.startsWith('list'));

const closureEvents = computed(() =>
    closures.value.map(c => ({
        start: c.startDate,
        end: nextDayKey(c.endDate),
        allDay: true,
        editable: false,
        display: (isListView.value ? 'list-item' : 'background') as 'list-item' | 'background',
        title: c.reason ? `Salle fermée — ${c.reason}` : 'Salle fermée',
        color: '#94a3b8',
        classNames: ['fc-closure-bg'],
        extendedProps: { closure: c },
    })),
);

const handleClosuresChanged = async () => {
    // reloadClosures() mutates `closures`, which flows through the
    // closureEvents computed into calendarOptions; the FullCalendar wrapper
    // deep-watches options and re-applies the closure source. refetchEvents()
    // additionally refreshes the games (function) source.
    await reloadClosures();
    calendarApi.value?.refetchEvents();
};

// ── Dialogs ───────────────────────────────────────────

const csvImportVisible = ref(false);
const closureDialogVisible = ref(false);

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

const cellDateKey = toDateKey;

const isMobile = () => typeof window !== 'undefined' && window.innerWidth < 768;

const switchView = (view: CalendarViewType) => {
    activeView.value = view;
    calendarApi.value?.changeView(view);
};

// ── Calendar options ──────────────────────────────────

// Computed so that `readonly` stays reactive if it ever changes at runtime
const calendarOptions = computed<CalendarOptions>(() => ({
    plugins: [dayGridPlugin, listPlugin, multiMonthPlugin, interactionPlugin],
    initialView: isMobile() ? 'listMonth' : 'dayGridMonth',
    locale: frLocale,
    headerToolbar: false,
    editable: !props.readonly,
    selectable: !props.readonly,
    selectMirror: !props.readonly,
    dayCellClassNames: (arg) => isHomeDay(arg.date) ? ['fc-home-day'] : [],
    dayHeaderClassNames: (arg) => isHomeDay(arg.date) ? ['fc-home-col'] : [],
    dayMaxEvents: true,
    weekends: true,
    height: '100%',
    eventSources: [
        { events: eventSourceFn },
        { events: closureEvents.value },
    ],
    eventsSet: () => recomputeCounts(calendarApi.value),
    // Built as DOM nodes with textContent: user-provided values (team name,
    // opponent) are rendered as plain text natively — no XSS, no manual escaping
    eventContent: (arg) => {
        const el = (tag: string, className: string, text?: string): HTMLElement => {
            const node = document.createElement(tag);
            node.className = className;
            if (text !== undefined) node.textContent = text;
            return node;
        };

        // Closure events (list view): lock icon + reason, no game data
        const closure = arg.event.extendedProps.closure;
        if (closure) {
            const wrap = el('div', 'fc-closure-event');
            const icon = el('i', 'pi pi-lock');
            wrap.append(icon, el('span', 'fc-closure-event__label', arg.event.title));
            return { domNodes: [wrap] };
        }

        const game: Game = arg.event.extendedProps.game;
        const emoji = game?.venue === GameVenue.HOME ? '🏠' : '✈️';

        const main = el('div', 'fc-event-main');
        main.append(
            el('span', 'fc-event-team', `${game?.team?.name ?? ''} vs.`),
            el('span', 'fc-event-opponent', game?.opponent ?? ''),
        );

        const inner = el('div', 'fc-event-inner');
        inner.append(main, el('span', 'fc-event-venue', emoji));

        return { domNodes: [inner] };
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
        const game = info.event.extendedProps.game as Game | undefined;
        if (!game) return; // closure event: not clickable
        detailDialog.game = game;
        detailDialog.visible = true;
    },
    select: props.readonly ? undefined : (info: DateSelectArg) => {
        openCreateDialog(info.start);
        calendarApi.value?.unselect();
    },
    eventDrop: props.readonly ? undefined : handleEventDrop,
    datesSet: (info) => {
        currentTitle.value = info.view.title;
        currentViewType.value = info.view.type;
    },
}));

// ── Lifecycle ─────────────────────────────────────────

let resizeObserver: ResizeObserver | null = null;
let resizeFrame = 0;

onMounted(() => {
    if (calendarWrapperRef.value) {
        // Coalesce resize bursts (e.g. animated sidebar) into one update per frame
        resizeObserver = new ResizeObserver(() => {
            cancelAnimationFrame(resizeFrame);
            resizeFrame = requestAnimationFrame(() => calendarApi.value?.updateSize());
        });
        resizeObserver.observe(calendarWrapperRef.value);
    }
});

onUnmounted(() => {
    resizeObserver?.disconnect();
    cancelAnimationFrame(resizeFrame);
});
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

/* Home-day columns: class set by dayHeaderClassNames from HOME_DAYS,
   single source of truth with the cell highlight above */
:deep(.fc-col-header-cell.fc-home-col .fc-col-header-cell-cushion) {
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

/* Badges group (lock + home count can both show on a closed day) */
:deep(.fc-cell-badges) {
    display: flex;
    align-items: center;
    gap: 2px;
    min-width: 0;
    overflow: hidden;
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

/* Closed-day (salle fermée) background + lock badge */
:deep(.fc-closure-bg) {
    background: repeating-linear-gradient(
        45deg,
        color-mix(in srgb, var(--p-text-muted-color) 14%, transparent),
        color-mix(in srgb, var(--p-text-muted-color) 14%, transparent) 6px,
        transparent 6px,
        transparent 12px
    );
}

:deep(.fc-closure-badge) {
    display: inline-flex;
    align-items: center;
    font-size: 0.55rem;
    font-weight: 600;
    background: color-mix(in srgb, var(--p-text-muted-color) 20%, transparent);
    color: var(--p-text-color);
    border-radius: 3px;
    padding: 0 3px;
    line-height: 1.3;
}

/* Closure entry shown in list view (mobile) */
:deep(.fc-closure-event) {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--p-text-muted-color);
    font-weight: 500;
}

:deep(.fc-closure-event .pi-lock) {
    font-size: 0.8rem;
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
