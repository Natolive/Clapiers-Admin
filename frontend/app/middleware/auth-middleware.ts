import type {AppUserRole} from "~/types/entity/AppUser";

export default defineNuxtRouteMiddleware(async (to) => {
    const auth = useAuthStore()

    if (!auth.isAuthenticated && to.path !== '/login') {
        return navigateTo('/login')
    } else {
        try {
            await auth.refresh();
        } catch (e) {
            return navigateTo('/login')
        }
    }

    const requiredRoles = to.meta.requiredRoles as string[] | undefined
    if (requiredRoles && requiredRoles.length > 0) {
        const { hasRole } = useUserRole()
        const redirectTo = (to.meta.redirectTo as string | undefined) ?? '/dashboard/calendar'
        if (!requiredRoles.some(role => hasRole(role as AppUserRole))) {
            return navigateTo(redirectTo)
        }
    }
})