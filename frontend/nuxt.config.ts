import Aura from '@primeuix/themes/aura';

export default defineNuxtConfig({
    compatibilityDate: '2026-01-01',
    devServer: {
        host: '0.0.0.0',
        port: 3000,
    },
    vite: {
        server: {
            allowedHosts: ['app.clapiers.local'],
        },
    },
    runtimeConfig: {
        public: {
            apiBase: process.env.NUXT_PUBLIC_API_BASE ?? 'http://127.0.0.1:8000/api',
        }
    },
    app: {
        head: {
            titleTemplate: '%s - Clapiers Admin',
        },
        pageTransition: {
            name: 'page',
            mode: 'out-in'
        },
        layoutTransition: {
            name: 'layout',
            mode: 'out-in'
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
        '~/assets/css/home.css'
    ]
})