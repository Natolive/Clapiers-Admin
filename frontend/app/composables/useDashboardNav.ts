import { AppUserRole } from '~/types/entity/AppUser';

export type DashboardMenuItem = {
    label: string;
    icon: string;
    route?: string;
    command?: () => void;
    items?: DashboardMenuItem[];
};

/**
 * Role-aware dashboard navigation: menu items and current page title.
 */
export const useDashboardNav = (onNavigate?: () => void) => {
    const route = useRoute();
    const { isSuperAdmin, isAdmin, hasRole } = useUserRole();

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
            items.push({ label: 'Historique des matchs', icon: 'pi pi-history', route: '/dashboard/game-history', command: go('/dashboard/game-history') });
        }

        if (isAdmin.value) {
            items.push({ label: 'Mon équipe', icon: 'pi pi-users', route: '/dashboard/my-team', command: go('/dashboard/my-team') });
        }

        if (hasRole(AppUserRole.VIEW_MESSAGE)) {
            items.push({
                label: 'Messages',
                icon: 'pi pi-envelope',
                route: '/dashboard/messages',
                command: go('/dashboard/messages')
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

    return { navigationItems, pageTitle };
};
