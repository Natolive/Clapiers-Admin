export function useApi() {
    const config = useRuntimeConfig()
    const baseURL = config.public.apiBase

    // Use $fetch for logic-heavy or class-based requests
    return $fetch.create({
        baseURL,
        // You can add interceptors here (e.g., for headers/auth)
        onRequest({options}) {
             const token = useCookie('auth_token').value
            if (token) options.headers.set('Authorization', `Bearer ${token}`)
        }
    })
}