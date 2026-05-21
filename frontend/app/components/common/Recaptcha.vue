<template>
  <div v-if="siteKey" class="recaptcha-widget">
    <ClientOnly>
      <div ref="rootEl" />
    </ClientOnly>
  </div>
</template>

<script setup lang="ts">
import { useChallengeV2, useRecaptchaProvider } from 'vue-recaptcha'

const token = defineModel<string>({ default: '' })

const siteKey = useRuntimeConfig().public.recaptchaSiteKey as string

const rootEl = ref<HTMLElement | null>(null)
let challenge: ReturnType<typeof useChallengeV2> | null = null

if (siteKey) {
  useRecaptchaProvider()
  challenge = useChallengeV2({ root: rootEl })
  challenge.onVerify((t: string) => { token.value = t })
  challenge.onExpired(() => { token.value = '' })
  challenge.onError(() => { token.value = '' })
}

const reset = () => {
  challenge?.reset()
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
