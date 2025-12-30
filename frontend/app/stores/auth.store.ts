import { defineStore } from 'pinia'
import type {Credentials} from "~/types/custom/Credentials";
import {AuthenticationRepository} from "~/repository/authentication-repository";
import type {AppUser} from "~/types/entity/AppUser";
import { useSeasonsStore } from './seasons.store';

export const useAuthStore = defineStore('auth', () => {
    const token = useCookie('auth_token')
    const user = ref<AppUser | null>(null)

    const isAuthenticated = computed(() => !!token.value)

    async function login(credentials: Credentials) {
        const authenticateRepository = new AuthenticationRepository();
        const data = await authenticateRepository.login(credentials);
        token.value = data.token
        await new Promise(resolve => setTimeout(resolve, 100));
        await refresh();
    }

    async function refresh() {
        const authenticateRepository = new AuthenticationRepository();
        const userData = await authenticateRepository.me();
        user.value = userData

        // Fetch actual season after user data is loaded
        const seasonsStore = useSeasonsStore();
        await seasonsStore.fetchActualSeason();
    }

    function logout() {
        token.value = null // Clears the cookie
        user.value = null

        // Clear actual season on logout
        const seasonsStore = useSeasonsStore();
        seasonsStore.clearActualSeason();

        navigateTo('/login')
    }

    return { token, user, isAuthenticated, login, logout, refresh }
})