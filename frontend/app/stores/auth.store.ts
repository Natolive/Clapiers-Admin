import { defineStore } from 'pinia'
import type {Credentials} from "~/types/custom/Credentials";
import {AuthenticationRepository} from "~/repository/authentication-repository";
import type {AppUser} from "~/types/entity/AppUser";

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
    }

    function logout() {
        token.value = null // Clears the cookie
        user.value = null

        navigateTo('/')
    }

    return { token, user, isAuthenticated, login, logout, refresh }
})