<template>
  <AuthCard title="Mot de passe oublié" subtitle="Recevez un lien pour en choisir un nouveau">
    <Message v-if="sent" severity="success" :closable="false">
      {{ sent }} Pensez à vérifier vos spams. Le lien est valable 1 heure.
    </Message>

    <form v-else class="flex flex-column gap-3" @submit.prevent="submit">
      <div class="flex flex-column gap-2">
        <label for="forgot-email" class="font-bold">Adresse e-mail</label>
        <InputText id="forgot-email" v-model="email" type="email" fluid autocomplete="username" placeholder="exemple@email.com" />
      </div>
      <CommonRecaptcha ref="recaptcha" v-model="recaptchaToken" />
      <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
      <Button type="submit" label="Recevoir le lien" icon="pi pi-send" class="w-full mt-2" :loading="sending" :disabled="!email.trim()" />
    </form>

    <NuxtLink to="/login" class="block text-center mt-4 text-primary no-underline text-sm font-medium">Retour à la connexion</NuxtLink>
  </AuthCard>
</template>

<script setup lang="ts">
import { UserRepository } from '~/repository/user-repository'

useSeoMeta({ title: 'Mot de passe oublié — Clapiers Volley-Ball', robots: 'noindex' })

const repository = new UserRepository()
const recaptchaEnabled = !!useRuntimeConfig().public.recaptchaSiteKey

const email = ref('')
const recaptchaToken = ref('')
const recaptcha = ref<{ reset: () => void } | null>(null)
const sending = ref(false)
const error = ref('')
const sent = ref('')

const submit = async () => {
  if (recaptchaEnabled && !recaptchaToken.value) {
    error.value = 'Veuillez valider le captcha'
    return
  }
  sending.value = true
  error.value = ''
  try {
    sent.value = (await repository.requestPasswordReset(email.value.trim(), recaptchaToken.value)).message
  } catch (e: any) {
    error.value = apiErrorMessage(e)
    recaptcha.value?.reset()
  } finally {
    sending.value = false
  }
}
</script>
