import Aura from '@primeuix/themes/aura';

export default defineNuxtConfig({
    ssr: true,
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
            htmlAttrs: { lang: 'fr' },
            charset: 'utf-8',
            viewport: 'width=device-width, initial-scale=1',
            meta: [
                { name: 'google-site-verification', content: 'pn8nl5hSqc7don08zbejN8u2Gb4vnaPNdj8-9JFg4pA' },
                { property: 'og:site_name', content: 'Clapiers Volley Ball' },
                { property: 'og:type', content: 'website' },
                { name: 'twitter:card', content: 'summary_large_image' },
                { property: 'og:image', content: 'https://clapiersvb.fr/logo.png' },
                { name: 'twitter:image', content: 'https://clapiersvb.fr/logo.png' },
            ],
            link: [
                { rel: 'icon', type: 'image/x-icon', href: '/favicon.ico' },
                { rel: 'icon', type: 'image/png', sizes: '16x16', href: '/favicon-16x16.png' },
                { rel: 'icon', type: 'image/png', sizes: '32x32', href: '/favicon-32x32.png' },
                { rel: 'apple-touch-icon', sizes: '180x180', href: '/apple-touch-icon.png' },
                { rel: 'manifest', href: '/site.webmanifest' },
            ],
            script: [
                {
                    type: 'application/ld+json',
                    innerHTML: JSON.stringify({
                        '@context': 'https://schema.org',
                        '@type': 'SportsOrganization',
                        name: 'Clapiers Volley Ball',
                        url: 'https://clapiersvb.fr',
                        sport: 'Volleyball',
                        email: 'info@clapiersvb.fr',
                        telephone: '+33609851673',
                        address: {
                            '@type': 'PostalAddress',
                            streetAddress: '1 Rue du Paraguay',
                            addressLocality: 'Clapiers',
                            postalCode: '34830',
                            addressCountry: 'FR',
                        },
                        location: {
                            '@type': 'SportsActivityLocation',
                            name: 'Gymnase Joël Abati',
                            address: {
                                '@type': 'PostalAddress',
                                streetAddress: '1 Rue du Paraguay',
                                addressLocality: 'Clapiers',
                                postalCode: '34830',
                                addressCountry: 'FR',
                            },
                        },
                    }),
                },
            ],
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
    modules: ['@primevue/nuxt-module', '@pinia/nuxt', '@nuxtjs/sitemap'],
    site: {
        url: 'https://clapiersvb.fr',
    },
    routeRules: {
        '/': { prerender: true },
        '/club': { prerender: true },
        '/horaires': { prerender: true },
        '/calendrier': { prerender: true },
        '/contact': { prerender: true },
        '/inscriptions': { prerender: true },
        '/tarifs': { prerender: true },
        '/documents': { prerender: true },
        '/login': { ssr: false },
        '/dashboard/**': { ssr: false },
        '/dashboard': { ssr: false },
    },
    sitemap: {
        discoverImages: true,
        exclude: ['/login', '/dashboard/**'],
    },
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
        '~/assets/css/global.css',
        '~/assets/css/home.css'
    ]
})