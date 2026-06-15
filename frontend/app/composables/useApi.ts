export function useApi() {
    const config = useRuntimeConfig();
    const toast = usePVToastService();


    const baseURL = config.public.apiBase

    return $fetch.create({
        baseURL,
        onRequest({ options }) {
            const token = useCookie('auth_token').value
            if (token) options.headers.set('Authorization', `Bearer ${token}`)
        },
        onResponseError({ request, response }) {
            const message = response._data?.message || response.statusText || 'An error occurred'
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: message,
                life: 5000
            });

            // 401 = session expirée → logout + redirection. Exception : un 401
            // de /login signifie « identifiants invalides » — on affiche le
            // toast et on reste sur la page de connexion.
            const url = typeof request === 'string' ? request : request.url
            if (response.status === 401 && !url.endsWith('/login')) {
                const authStore = useAuthStore()
                authStore.logout();
            }
        }
    })
}