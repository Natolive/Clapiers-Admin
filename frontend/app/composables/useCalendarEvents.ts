import { ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import type { CalendarApi, EventInput, EventDropArg, EventSourceFuncArg } from '@fullcalendar/core';
import type { Game } from '~/types/entity/Game';
import { GameVenue } from '~/types/enum/GameVenue';
import { getTeamColor } from '~/utils/teamColors';
import { GameRepository } from '~/repository/game-repository';

type FetchFn = (params: { start: string; end: string; teamId?: number | null }) => Promise<Game[]>;

export function useCalendarEvents(
    calendarApi: ComputedRef<CalendarApi | undefined>,
    fetchFn: FetchFn,
    selectedTeamId: Ref<number | null>,
    isReadonly: Ref<boolean>,
    isSuperAdmin: Ref<boolean>,
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
                teamId: selectedTeamId.value,
            });
            successCallback(games.map(gameToEvent));
        } catch (err) {
            failureCallback(err as Error);
            if (!isReadonly.value) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les matchs', life: 4000 });
            }
        }
    };

    // ── Mutations locales ─────────────────────────────

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

    // ── Drag & drop ───────────────────────────────────

    const handleEventDrop = async (info: EventDropArg) => {
        const game = info.event.extendedProps.game as Game;
        const start = info.event.start;
        if (!start) { info.revert(); return; }

        const dateStr = `${start.getFullYear()}-${String(start.getMonth() + 1).padStart(2, '0')}-${String(start.getDate()).padStart(2, '0')}`;

        try {
            const updated = await gameRepository.update(game.id, {
                opponent:    game.opponent,
                date:        dateStr,
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
