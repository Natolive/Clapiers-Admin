export function useApi() {
    const config = useRuntimeConfig()
    const baseURL = config.public.apiBase
    const toast = useToast()

    return $fetch.create({
        baseURL,
        onRequest({options}) {
            const token = useCookie('auth_token').value
            if (token) options.headers.set('Authorization', `Bearer ${token}`)
        },
        onResponseError({response}) {
            const message = response._data?.message || response.statusText || 'An error occurred'

            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: message,
                life: 5000
            })
        }
    })
}