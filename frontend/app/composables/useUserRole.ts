import { AppUserRole } from "~/types/entity/AppUser";

export const useUserRole = () => {
    const authStore = useAuthStore();

    const user = computed(() => authStore.user);

    /**
     * Role hierarchy:
     * - SUPER_ADMIN has all permissions (is also ADMIN and USER)
     * - ADMIN is also USER
     * - USER is just USER
     */
    const roleHierarchy: Record<AppUserRole, AppUserRole[]> = {
        [AppUserRole.SUPER_ADMIN]: [AppUserRole.SUPER_ADMIN, AppUserRole.ADMIN, AppUserRole.USER],
        [AppUserRole.ADMIN]: [AppUserRole.ADMIN, AppUserRole.USER],
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

    return {
        user,
        hasRole,
        hasExactRole,
        isSuperAdmin,
        isAdmin,
        isUser
    };
};
