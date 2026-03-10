export const useNationalities = () => {
    const config = useRuntimeConfig();

    const { data: nationalities } = useAsyncData<string[]>(
        'nationalities',
        () => $fetch(`${config.public.apiBase}/public/nationalities`),
        { default: () => [] }
    );

    return { nationalities };
};
