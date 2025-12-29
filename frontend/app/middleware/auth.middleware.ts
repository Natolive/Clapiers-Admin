import {AuthenticationRepository} from "~/repository/authentication-repository";

export default defineNuxtRouteMiddleware(async (to, from) => {
    const auth = useAuthStore()

    // If the user is NOT logged in and trying to access a private page
    if (!auth.isAuthenticated && to.path !== '/login') {
        return navigateTo('/login')
    } else {
        try {
            await auth.refresh();
        } catch (e) {
            return navigateTo('/login')
        }
    }
})