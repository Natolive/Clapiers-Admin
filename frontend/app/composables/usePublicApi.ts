export function usePublicApi() {
    const config = useRuntimeConfig();
    const baseURL = config.public.apiBase

    return $fetch.create({
        baseURL
    })
}
