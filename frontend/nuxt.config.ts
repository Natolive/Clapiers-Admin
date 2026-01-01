import Aura from '@primeuix/themes/aura';

export default defineNuxtConfig({
    compatibilityDate: '2026-01-01',
    runtimeConfig: {
        public: {
            apiBase: 'http://127.0.0.1:8000/api', // accessible on client
        }
    },
    modules: ['@primevue/nuxt-module', '@pinia/nuxt'],
    primevue: {
        autoImport: true,
        options: {
            theme: {
                preset: Aura,
                options: {
                    darkModeSelector: 'light',
                    cssLayer: false
                }
            }
        }
    },
    css: [
        'primeflex/primeflex.css',
        'primeicons/primeicons.css',
        // We don't import theme CSS here anymore!
        // It's handled by the preset below.
    ]
})