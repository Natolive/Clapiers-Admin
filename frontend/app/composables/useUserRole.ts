import { AppUserRole } from "~/types/entity/AppUser";

export const useUserRole = () => {
    const authStore = useAuthStore();

    const user = computed(() => authStore.user);

    /**
     * Role hierarchy:
     * - SUPER_ADMIN has all permissions
     * - ADMIN is also USER
     * - CONFIRM_MESSAGE can also VIEW_MESSAGE
     * - VIEW_MESSAGE is also USER
     * - USER is just USER
     */
    const roleHierarchy: Record<AppUserRole, AppUserRole[]> = {
        [AppUserRole.SUPER_ADMIN]: [
            AppUserRole.SUPER_ADMIN,
            AppUserRole.ADMIN,
            AppUserRole.USER,
            AppUserRole.VIEW_MESSAGE,
            AppUserRole.CONFIRM_MESSAGE
        ],
        [AppUserRole.ADMIN]: [AppUserRole.ADMIN, AppUserRole.USER],
        [AppUserRole.CONFIRM_MESSAGE]: [AppUserRole.CONFIRM_MESSAGE, AppUserRole.VIEW_MESSAGE, AppUserRole.USER],
        [AppUserRole.VIEW_MESSAGE]: [AppUserRole.VIEW_MESSAGE, AppUserRole.USER],
        [AppUserRole.USER]: [AppUserRole.USER]
    };

    /**
     * Check if user has a specific role (considering hierarchy)
     */
    const hasRole = (role: AppUserRole): boolean => {
        if (!user.value || !user.value.roles) {
            return false;
        }

        // Check if user has any role that grants access to the requested role
        return user.value.roles.some(userRole =>
            roleHierarchy[userRole]?.includes(role)
        );
    };

    /**
     * Check if user has the exact role (without hierarchy)
     */
    const hasExactRole = (role: AppUserRole): boolean => {
        if (!user.value || !user.value.roles) {
            return false;
        }

        return user.value.roles.includes(role);
    };

    /**
     * Helper computed properties for role checks
     */
    const isSuperAdmin = computed(() => hasRole(AppUserRole.SUPER_ADMIN));
    const isAdmin = computed(() => hasRole(AppUserRole.ADMIN));
    const isUser = computed(() => hasRole(AppUserRole.USER));

    /**
     * Get human-readable label for a role
     */
    const getRoleLabel = (role: string): string => {
        const roleMap: Record<string, string> = {
            [AppUserRole.SUPER_ADMIN]: 'Super Admin',
            [AppUserRole.ADMIN]: 'Admin',
            [AppUserRole.USER]: 'Utilisateur',
            [AppUserRole.VIEW_MESSAGE]: 'Voir messages',
            [AppUserRole.CONFIRM_MESSAGE]: 'Gérer messages'
        };
        return roleMap[role] || role;
    };

    /**
     * Get badge severity for a role
     */
    const getRoleSeverity = (role: string): 'danger' | 'info' | 'secondary' => {
        if (role === AppUserRole.SUPER_ADMIN) return 'danger';
        if (role === AppUserRole.ADMIN) return 'info';
        return 'secondary';
    };

    return {
        user,
        hasRole,
        hasExactRole,
        isSuperAdmin,
        isAdmin,
        isUser,
        getRoleLabel,
        getRoleSeverity
    };
};
