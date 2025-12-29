import { defineStore } from 'pinia'
import type {Credentials} from "~/types/custom/Credentials";
import {AuthenticationRepository} from "~/repository/authentication-repository";

export const useAuthStore = defineStore('auth', () => {
    const token = useCookie('auth_token')

    const isAuthenticated = computed(() => !!token.value)
    const authenticateRepository = new AuthenticationRepository();

    async function login(credentials: Credentials) {
        const data = await authenticateRepository.login(credentials);
        token.value = data.token
        await refresh();
    }

    async function refresh() {
        const user = await authenticateRepository.me();
    }

    function logout() {
        token.value = null // Clears the cookie
        navigateTo('/login')
    }

    return { token, isAuthenticated, login, logout, refresh }
})