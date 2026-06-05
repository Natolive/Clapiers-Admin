import type { Ref } from 'vue';

export interface UseInfiniteScrollOptions {
    /** Distance before the sentinel at which loading triggers (default '200px') */
    rootMargin?: string;
}

export interface UseInfiniteScrollReturn {
    /**
     * Re-evaluates the sentinel visibility and fires `onReach` if it is still
     * in view. Call it after appending items: if the new content does not push
     * the sentinel off-screen (short result set, tall viewport), the observer
     * never re-fires on its own and the list would stall.
     */
    recheck: () => void;
}

/**
 * Calls `onReach` whenever the `target` element approaches the viewport.
 * Used as a sentinel at the bottom of a list for infinite scrolling.
 */
export const useInfiniteScroll = (
    target: Ref<HTMLElement | null>,
    onReach: () => void,
    options: UseInfiniteScrollOptions = {}
): UseInfiniteScrollReturn => {
    let observer: IntersectionObserver | null = null;

    const stop = (): void => {
        observer?.disconnect();
        observer = null;
    };

    const observe = (element: HTMLElement): void => {
        observer = new IntersectionObserver(
            (entries) => {
                if (entries.some(entry => entry.isIntersecting)) onReach();
            },
            { rootMargin: options.rootMargin ?? '200px' }
        );
        observer.observe(element);
    };

    // Re-observing always triggers an initial callback with the current state
    const recheck = (): void => {
        const element = target.value;
        if (!observer || !element) return;
        observer.unobserve(element);
        observer.observe(element);
    };

    watch(target, (element) => {
        stop();
        if (element) observe(element);
    }, { immediate: true, flush: 'post' });

    onUnmounted(stop);

    return { recheck };
};
