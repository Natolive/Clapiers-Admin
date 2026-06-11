/**
 * Détection réactive « petit écran » basée sur matchMedia.
 * SSR-safe : false côté serveur, résolu au mount.
 *
 * const isMobile = useIsMobile();      // < 768px
 * const isNarrow = useIsMobile(640);   // < 640px
 */
export const useIsMobile = (breakpoint = 768) => {
  const isMobile = ref(false);
  let query: MediaQueryList | null = null;
  const update = () => { isMobile.value = query?.matches ?? false; };

  onMounted(() => {
    query = window.matchMedia(`(max-width: ${breakpoint - 1}px)`);
    update();
    query.addEventListener('change', update);
  });

  onUnmounted(() => {
    query?.removeEventListener('change', update);
  });

  return readonly(isMobile);
};
