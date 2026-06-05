import type { Ref } from 'vue';

export interface UseInfiniteScrollOptions {
    /** Distance before the sentinel at which loading triggers (default '200px') */
    rootMargin?: string;
}

/**
 * Calls `onReach` whenever the `target` element approaches the viewport.
 * Used as a sentinel at the bottom of a list for infinite scrolling.
 */
export const useInfiniteScroll = (
    target: Ref<HTMLElement | null>,
    onReach: () => void,
    options: UseInfiniteScrollOptions = {}
): void => {
    let observer: IntersectionObserver | null = null;

    const stop = (): void => {
        observer?.disconnect();
        observer = null;
    };

    watch(target, (element) => {
        stop();
        if (!element) return;

        observer = new IntersectionObserver(
            (entries) => {
                if (entries.some(entry => entry.isIntersecting)) onReach();
            },
            { rootMargin: options.rootMargin ?? '200px' }
        );
        observer.observe(element);
    }, { immediate: true, flush: 'post' });

    onUnmounted(stop);
};
