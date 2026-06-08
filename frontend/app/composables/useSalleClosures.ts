import type { Ref } from 'vue';
import type { SalleClosure } from '~/types/entity/SalleClosure';
import { findClosureForDateKey } from '~/utils/calendarRules';

export type SalleClosureFetchFn = () => Promise<SalleClosure[]>;

export interface UseSalleClosuresReturn {
    closures: Ref<SalleClosure[]>;
    /** Reloads the list from the server */
    reload: () => Promise<void>;
    /** Returns the closure covering a given YYYY-MM-DD key, or null */
    closureForDateKey: (dateKey: string) => SalleClosure | null;
}

/**
 * Loads gym closures (low volume — fetched once, not per visible range)
 * and exposes a per-day lookup for the calendar grid and game form.
 */
export const useSalleClosures = (fetchFn: SalleClosureFetchFn): UseSalleClosuresReturn => {
    const closures = ref<SalleClosure[]>([]);

    const reload = async (): Promise<void> => {
        closures.value = await fetchFn().catch(() => []);
    };

    const closureForDateKey = (dateKey: string): SalleClosure | null =>
        findClosureForDateKey(closures.value, dateKey);

    onMounted(reload);

    return { closures, reload, closureForDateKey };
};
