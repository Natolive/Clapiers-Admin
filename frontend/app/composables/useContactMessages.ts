import type { Ref, ComputedRef } from 'vue';
import { ContactMessageRepository } from '~/repository/contact-message-repository';
import type { ContactMessage } from '~/types/entity/ContactMessage';

export interface UseContactMessagesReturn {
    /** Messages loaded so far (all pages accumulated) */
    messages: Ref<ContactMessage[]>;
    /** Total matching messages on the server */
    total: Ref<number>;
    /** Name/first-name search input (debounced) */
    search: Ref<string>;
    /** True during the initial load or a new search */
    loading: Ref<boolean>;
    /** True while the next page is being fetched */
    loadingMore: Ref<boolean>;
    /** True when more pages remain to be loaded */
    hasMore: ComputedRef<boolean>;
    loadFirstPage: () => Promise<void>;
    loadMore: () => Promise<void>;
}

const PAGE_SIZE = 20;
const SEARCH_DEBOUNCE_MS = 300;

/**
 * Paginated contact-message list state: infinite-scroll accumulation
 * and debounced name search.
 */
export const useContactMessages = (): UseContactMessagesReturn => {
    const repository = new ContactMessageRepository();

    const messages = ref<ContactMessage[]>([]);
    const total = ref(0);
    const search = ref('');
    const loading = ref(true);
    const loadingMore = ref(false);
    const page = ref(1);

    const hasMore = computed(() => messages.value.length < total.value);

    let debounceTimer: ReturnType<typeof setTimeout> | null = null;
    let requestId = 0;

    const fetchPage = async (pageToLoad: number, append: boolean): Promise<void> => {
        const currentRequest = ++requestId;
        const result = await repository.getPaginated({
            page: pageToLoad,
            limit: PAGE_SIZE,
            search: search.value.trim() || undefined,
        });

        // A newer request was fired in the meantime (e.g. search changed): drop this response
        if (currentRequest !== requestId) return;

        total.value = result.total;
        page.value = pageToLoad;
        messages.value = append ? [...messages.value, ...result.data] : result.data;
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

    watch(search, () => {
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(loadFirstPage, SEARCH_DEBOUNCE_MS);
    });

    onUnmounted(() => {
        if (debounceTimer) clearTimeout(debounceTimer);
    });

    return { messages, total, search, loading, loadingMore, hasMore, loadFirstPage, loadMore };
};
