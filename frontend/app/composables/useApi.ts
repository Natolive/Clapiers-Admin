export function useApi() {
    const config = useRuntimeConfig()

    const baseURL = config.public.apiBase

    return $fetch.create({
        baseURL,
        onRequest({ options }) {
            const token = useCookie('auth_token').value
            if (token) options.headers.set('Authorization', `Bearer ${token}`)
        },
        onResponseError({ response }) {
            // Check if it's an authentication error
            if (response.status === 401) {
                const authStore = useAuthStore()
                authStore.logout()
                return;
            }

            // Show error toast for other errors
            const message = response._data?.message || response.statusText || 'An error occurred'
            const toast = useToast();
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: message,
                life: 5000
            });
        }
    })
}