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
 *
 * Structure d'une entrée (`DashboardMenuItem`) :
 * - lien simple  → `route` + `command` (rendu comme nav-link cliquable) ;
 * - catégorie    → clé `items: [...]` (rendu comme titre de groupe + sous-liste).
 * Le template choisit l'un ou l'autre via `v-if="item.items"`.
 */
export const useDashboardNav = (onNavigate?: () => void) => {
    const route = useRoute();
    const { isSuperAdmin, isAdmin, hasRole } = useUserRole();

    const go = (path: string) => () => {
        navigateTo(path);
        onNavigate?.();
    };

    const link = (label: string, icon: string, route: string): DashboardMenuItem =>
        ({ label, icon, route, command: go(route) });

    /** Construit une catégorie à partir des liens non nuls ; renvoie null si vide. */
    const group = (label: string, icon: string, links: (DashboardMenuItem | false)[]): DashboardMenuItem | null => {
        const items = links.filter(Boolean) as DashboardMenuItem[];
        return items.length ? { label, icon, items } : null;
    };

    const navigationItems = computed<DashboardMenuItem[]>(() => {
        const su = isSuperAdmin.value;
        const admin = isAdmin.value;
        const canMsg = hasRole(AppUserRole.VIEW_MESSAGE);

        const sections: (DashboardMenuItem | null)[] = [
            // ── Principal (sans catégorie) ──────────────────────────
            su && link('Tableau de bord', 'pi pi-home', '/dashboard'),
            link('Calendrier', 'pi pi-calendar', '/dashboard/calendar'),
            canMsg && link('Messages', 'pi pi-envelope', '/dashboard/messages'),

            // ── Catégories ──────────────────────────────────────────
            group('Compétition', 'pi pi-flag', [
                su && link('Historique des matchs', 'pi pi-history', '/dashboard/game-history'),
                admin && link('Mon équipe', 'pi pi-users', '/dashboard/my-team'),
            ]),
            group('Licences', 'pi pi-id-card', [
                su && link('Licenciés', 'pi pi-id-card', '/dashboard/settings/members'),
                su && link('Demandes de licence', 'pi pi-inbox', '/dashboard/settings/license-requests'),
            ]),
            group('Organisation', 'pi pi-sitemap', [
                su && link('Utilisateurs', 'pi pi-user', '/dashboard/settings/users'),
                su && link('Équipes', 'pi pi-sitemap', '/dashboard/settings/teams'),
            ]),
            group('Système', 'pi pi-cog', [
                su && link('Général', 'pi pi-sliders-h', '/dashboard/settings/general'),
                su && link('Logs', 'pi pi-list', '/dashboard/settings/logs'),
            ]),
        ];

        return sections.filter(Boolean) as DashboardMenuItem[];
    });

    const pageTitle = computed(() => {
        const flat = navigationItems.value.flatMap(item => item.items ?? [item]);
        return flat.find(item => item.route === route.path)?.label || 'Tableau de bord';
    });

    return { navigationItems, pageTitle };
};
