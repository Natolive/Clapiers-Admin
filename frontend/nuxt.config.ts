import Aura from '@primeuix/themes/aura';

export default defineNuxtConfig({
    runtimeConfig: {
        public: {
            apiBase: 'http://127.0.0.1:8000/api', // accessible on client
        }
    },
    app: {
        pageTransition: { name: 'page', mode: 'out-in' },
    },
    modules: ['@primevue/nuxt-module', '@pinia/nuxt'],
    css: [
        'primeflex/primeflex.css',
        'primeicons/primeicons.css',
        // We don't import theme CSS here anymore!
        // It's handled by the preset below.
    ],
    primevue: {
        options: {
            theme: {
                preset: Aura,
                options: {
                    darkModeSelector: 'system',
                    cssLayer: false // Disabling layers often fixes "variable not found" issues in Nuxt
                }
            }
        }
    }
})