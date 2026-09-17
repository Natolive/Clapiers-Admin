import { defineStore } from 'pinia'
import type {Credentials} from "~/types/custom/Credentials";
import {AuthenticationRepository} from "~/repository/authentication-repository";
import type {AppUser} from "~/types/entity/AppUser";

/**
 * Durée de vie du cookie de session, alignée sur le `token_ttl` du back
 * (7 jours, lexik_jwt_authentication.yaml). Sans `maxAge`, Nuxt pose un cookie
 * de session : il disparaît à la fermeture du navigateur, et l'app paraissait
 * connectée bien après l'expiration du jeton avant d'éjecter l'utilisateur au
 * premier 401. Les deux durées doivent bouger ensemble.
 */
const TOKEN_MAX_AGE = 60 * 60 * 24 * 7;

export const useAuthStore = defineStore('auth', () => {
    const token = useCookie('auth_token', { maxAge: TOKEN_MAX_AGE, sameSite: 'lax' })
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