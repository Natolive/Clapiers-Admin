import { VueRecaptchaPlugin } from 'vue-recaptcha'

export default defineNuxtPlugin((nuxtApp) => {
  const siteKey = useRuntimeConfig().public.recaptchaSiteKey as string
  if (!siteKey) return
  nuxtApp.vueApp.use(VueRecaptchaPlugin, { v2SiteKey: siteKey })
})
