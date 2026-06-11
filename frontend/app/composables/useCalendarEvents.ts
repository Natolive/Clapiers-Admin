import { ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import type { CalendarApi, EventInput, EventDropArg, EventSourceFuncArg } from '@fullcalendar/core';
import type { Game } from '~/types/entity/Game';
import { GameVenue } from '~/types/enum/GameVenue';
import { getTeamColor } from '~/utils/teamColors';
import { toDateKey } from '~/utils/calendarRules';
import { GameRepository } from '~/repository/game-repository';

export type CalendarFetchFn = (params: { start: string; end: string }) => Promise<Game[]>;

export function useCalendarEvents(
    calendarApi: ComputedRef<CalendarApi | undefined>,
    fetchFn: CalendarFetchFn,
    selectedTeamIds: Ref<number[]>,
    isReadonly: Ref<boolean>,
) {
    const toast = usePVToastService();
    const gameRepository = new GameRepository();

    const homeCountByDate = ref<Record<string, number>>({});
    const teamDateMap = ref<Record<string, number>>({});

    // ── Helpers ───────────────────────────────────────

    const gameToEvent = (game: Game): EventInput => ({
        id: String(game.id),
        title: `vs ${game.opponent}`,
        start: game.date,
        allDay: true,
        backgroundColor: getTeamColor(game.team.id),
        borderColor: getTeamColor(game.team.id),
        extendedProps: { game },
    });

    const recomputeCounts = (api: CalendarApi | undefined) => {
        const counts: Record<string, number> = {};
        // One game max per team per day (enforced by the form warning);
        // maps `date|teamId` to the game id for duplicate detection
        const tdMap: Record<string, number> = {};
        api?.getEvents().forEach(e => {
            const g = e.extendedProps.game as Game | undefined;
            if (!g) return;
            if (g.venue === GameVenue.HOME) counts[g.date] = (counts[g.date] ?? 0) + 1;
            tdMap[`${g.date}|${g.team.id}`] = g.id;
        });
        homeCountByDate.value = counts;
        teamDateMap.value = tdMap;
    };

    // ── Event source (cache natif FullCalendar par plage) ─────────────────

    const eventSourceFn = async (
        fetchInfo: EventSourceFuncArg,
        successCallback: (events: EventInput[]) => void,
        failureCallback: (error: Error) => void,
    ) => {
        try {
            const games = await fetchFn({
                start: fetchInfo.startStr,
                end: fetchInfo.endStr,
            });
            // Multi-team filter applied client-side: volumes are small and
            // it keeps the public/private fetch functions identical
            const filtered = selectedTeamIds.value.length > 0
                ? games.filter(g => selectedTeamIds.value.includes(g.team.id))
                : games;
            successCallback(filtered.map(gameToEvent));
        } catch (err) {
            failureCallback(err as Error);
            if (!isReadonly.value) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les matchs', life: 4000 });
            }
        }
    };

    // ── Mutations locales ─────────────────────────────

    const handleGameSaved = (_game: Game) => {
        calendarApi.value?.refetchEvents();
    };

    const handleGameDeleted = (id: number) => {
        const api = calendarApi.value;
        if (!api) return;
        api.getEventById(String(id))?.remove();
        recomputeCounts(api);
    };

    // ── Drag & drop ───────────────────────────────────

    const handleEventDrop = async (info: EventDropArg) => {
        const game = info.event.extendedProps.game as Game;
        const start = info.event.start;
        if (!start) { info.revert(); return; }

        const dateStr = toDateKey(start);
        if (dateStr === game.date) return; // dropped on the same day: nothing to save

        try {
            await gameRepository.update(game.id, {
                opponent:    game.opponent,
                date:        dateStr,
                meetingTime: game.meetingTime,
                venue:       game.venue,
                location:    game.location,
                teamId:      game.team.id,
            });
            calendarApi.value?.refetchEvents();
            toast.add({ severity: 'success', summary: 'Match déplacé', life: 2000 });
        } catch {
            info.revert();
            toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de déplacer le match', life: 4000 });
        }
    };

    return {
        homeCountByDate,
        teamDateMap,
        eventSourceFn,
        recomputeCounts,
        handleGameSaved,
        handleGameDeleted,
        handleEventDrop,
    };
}
