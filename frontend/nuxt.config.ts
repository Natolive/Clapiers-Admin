import Aura from '@primeuix/themes/aura';

export default defineNuxtConfig({
    modules: [
        '@primevue/nuxt-module'
    ],
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