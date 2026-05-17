<template>
  <div v-if="siteKey" ref="containerEl" class="recaptcha-widget"></div>
</template>

<script setup lang="ts">
const token = defineModel<string>({ default: '' })

const config = useRuntimeConfig()
const siteKey = config.public.recaptchaSiteKey as string

const containerEl = ref<HTMLElement | null>(null)
let widgetId: number | null = null

declare global {
  interface Window {
    grecaptcha?: {
      render: (el: HTMLElement, opts: {
        sitekey: string
        callback?: (token: string) => void
        'expired-callback'?: () => void
        'error-callback'?: () => void
      }) => number
      getResponse: (id?: number) => string
      reset: (id?: number) => void
    }
  }
}

if (siteKey) {
  useHead({
    script: [
      { src: 'https://www.google.com/recaptcha/api.js?render=explicit', async: true, defer: true }
    ]
  })
}

const renderWidget = () => {
  if (!window.grecaptcha || !containerEl.value || widgetId !== null) return false
  widgetId = window.grecaptcha.render(containerEl.value, {
    sitekey: siteKey,
    callback: (t: string) => { token.value = t },
    'expired-callback': () => { token.value = '' },
    'error-callback': () => { token.value = '' },
  })
  return true
}

onMounted(() => {
  if (!siteKey) return
  if (renderWidget()) return
  const interval = setInterval(() => {
    if (renderWidget()) clearInterval(interval)
  }, 200)
  setTimeout(() => clearInterval(interval), 10000)
})

const reset = () => {
  if (window.grecaptcha && widgetId !== null) {
    window.grecaptcha.reset(widgetId)
  }
  token.value = ''
}

defineExpose({ reset })
</script>

<style scoped>
.recaptcha-widget {
  display: flex;
  justify-content: center;
}
</style>
