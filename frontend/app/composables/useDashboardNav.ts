import { AppUserRole } from '~/types/entity/AppUser';
import { ContactMessageRepository } from '~/repository/contact-message-repository';

export type DashboardMenuItem = {
    label: string;
    icon: string;
    route?: string;
    command?: () => void;
    items?: DashboardMenuItem[];
    badge?: string;
};

const UNREAD_POLL_INTERVAL = 30000;

/**
 * Role-aware dashboard navigation: menu items, current page title
 * and unread messages badge (polled every 30s).
 */
export const useDashboardNav = (onNavigate?: () => void) => {
    const route = useRoute();
    const { isSuperAdmin, isAdmin, hasRole } = useUserRole();

    const unreadMessagesCount = ref(0);
    let pollInterval: ReturnType<typeof setInterval> | null = null;

    const go = (path: string) => () => {
        navigateTo(path);
        onNavigate?.();
    };

    const navigationItems = computed<DashboardMenuItem[]>(() => {
        const items: DashboardMenuItem[] = [
            { label: 'Calendrier', icon: 'pi pi-calendar', route: '/dashboard/calendar', command: go('/dashboard/calendar') },
        ];

        if (isSuperAdmin.value) {
            items.unshift({ label: 'Tableau de bord', icon: 'pi pi-home', route: '/dashboard', command: go('/dashboard') });
        }

        if (isAdmin.value) {
            items.push({ label: 'Mon équipe', icon: 'pi pi-users', route: '/dashboard/my-team', command: go('/dashboard/my-team') });
        }

        if (hasRole(AppUserRole.VIEW_MESSAGE)) {
            items.push({
                label: 'Messages',
                icon: 'pi pi-envelope',
                route: '/dashboard/messages',
                command: go('/dashboard/messages'),
                badge: unreadMessagesCount.value > 0 ? String(unreadMessagesCount.value) : undefined
            });
        }

        if (isSuperAdmin.value) {
            items.push({
                label: 'Paramètres',
                icon: 'pi pi-cog',
                items: [
                    { label: 'Utilisateurs', icon: 'pi pi-users', route: '/dashboard/settings/users', command: go('/dashboard/settings/users') },
                    { label: 'Équipes', icon: 'pi pi-sitemap', route: '/dashboard/settings/teams', command: go('/dashboard/settings/teams') },
                    { label: 'Licenciés', icon: 'pi pi-id-card', route: '/dashboard/settings/members', command: go('/dashboard/settings/members') },
                ]
            });
        }

        return items;
    });

    const pageTitle = computed(() => {
        const flat = navigationItems.value.flatMap(item => item.items ?? [item]);
        return flat.find(item => item.route === route.path)?.label || 'Tableau de bord';
    });

    const fetchUnreadMessages = async () => {
        if (!hasRole(AppUserRole.VIEW_MESSAGE)) return;
        try {
            const result = await new ContactMessageRepository().countUnread();
            unreadMessagesCount.value = result.count;
        } catch {}
    };

    onMounted(() => {
        fetchUnreadMessages();
        pollInterval = setInterval(fetchUnreadMessages, UNREAD_POLL_INTERVAL);
    });

    onUnmounted(() => {
        if (pollInterval) clearInterval(pollInterval);
    });

    return { navigationItems, pageTitle, unreadMessagesCount };
};
