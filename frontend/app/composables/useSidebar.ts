const SIDEBAR_FULL = '15rem';
const SIDEBAR_COLLAPSED = '4rem';
const DESKTOP_BREAKPOINT = 1024;

/**
 * Sidebar UI state: visibility, collapse/hover behavior and responsive layout.
 */
export const useSidebar = () => {
    const sidebarVisible = ref(false);
    const sidebarCollapsed = ref(true);
    const sidebarHovered = ref(false);
    const isDesktop = ref(false);

    const isCollapsedOnly = computed(() => sidebarCollapsed.value && isDesktop.value && !sidebarHovered.value);

    // Reactive margin avoids CSS sibling selector issues in scoped styles
    const mainStyle = computed(() => {
        if (!isDesktop.value) return { marginLeft: '0' };
        return { marginLeft: isCollapsedOnly.value ? SIDEBAR_COLLAPSED : SIDEBAR_FULL };
    });

    const closeSidebarOnMobile = () => {
        if (window.innerWidth < DESKTOP_BREAKPOINT) sidebarVisible.value = false;
    };

    const handleSidebarMouseEnter = () => {
        if (window.innerWidth >= DESKTOP_BREAKPOINT && sidebarCollapsed.value) sidebarHovered.value = true;
    };

    const handleSidebarMouseLeave = () => {
        sidebarHovered.value = false;
    };

    const handleResize = () => {
        isDesktop.value = window.innerWidth >= DESKTOP_BREAKPOINT;
        sidebarVisible.value = isDesktop.value;
    };

    onMounted(() => {
        handleResize();
        window.addEventListener('resize', handleResize);
    });

    onUnmounted(() => {
        window.removeEventListener('resize', handleResize);
    });

    return {
        sidebarVisible,
        sidebarCollapsed,
        sidebarHovered,
        isDesktop,
        isCollapsedOnly,
        mainStyle,
        closeSidebarOnMobile,
        handleSidebarMouseEnter,
        handleSidebarMouseLeave
    };
};
