import type { Ref, ComputedRef } from 'vue';
import { GameHistoryRepository } from '~/repository/game-history-repository';
import type { GameHistory } from '~/types/entity/GameHistory';

export interface UseGameHistoryReturn {
    /** Entries loaded so far (all pages accumulated) */
    entries: Ref<GameHistory[]>;
    /** Total matching entries on the server */
    total: Ref<number>;
    /** Optional team filter (null = all teams) */
    teamId: Ref<number | null>;
    /** True during the initial load or a filter change */
    loading: Ref<boolean>;
    /** True while the next page is being fetched */
    loadingMore: Ref<boolean>;
    /** True when more pages remain to be loaded */
    hasMore: ComputedRef<boolean>;
    loadFirstPage: () => Promise<void>;
    loadMore: () => Promise<void>;
}

const PAGE_SIZE = 20;

/**
 * Paginated game-history list state: infinite-scroll accumulation with an
 * optional team filter. Mirrors {@link useContactMessages}.
 */
export const useGameHistory = (): UseGameHistoryReturn => {
    const repository = new GameHistoryRepository();

    const entries = ref<GameHistory[]>([]);
    const total = ref(0);
    const teamId = ref<number | null>(null);
    const loading = ref(true);
    const loadingMore = ref(false);
    const page = ref(1);

    const hasMore = computed(() => entries.value.length < total.value);

    let requestId = 0;

    const fetchPage = async (pageToLoad: number, append: boolean): Promise<void> => {
        const currentRequest = ++requestId;
        const result = await repository.getPaginated({
            page: pageToLoad,
            limit: PAGE_SIZE,
            teamId: teamId.value ?? undefined,
        });

        // A newer request was fired in the meantime (e.g. filter changed): drop this response
        if (currentRequest !== requestId) return;

        total.value = result.total;
        page.value = pageToLoad;
        entries.value = append ? [...entries.value, ...result.data] : result.data;
    };

    const loadFirstPage = async (): Promise<void> => {
        loading.value = true;
        try {
            await fetchPage(1, false);
        } finally {
            loading.value = false;
        }
    };

    const loadMore = async (): Promise<void> => {
        if (loading.value || loadingMore.value || !hasMore.value) return;
        loadingMore.value = true;
        try {
            await fetchPage(page.value + 1, true);
        } finally {
            loadingMore.value = false;
        }
    };

    watch(teamId, loadFirstPage);

    return { entries, total, teamId, loading, loadingMore, hasMore, loadFirstPage, loadMore };
};
