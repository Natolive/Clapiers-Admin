<template>
  <AuthCard title="Nouveau mot de passe" subtitle="Choisissez votre nouveau mot de passe.">
    <div v-if="loading" class="flex justify-content-center">
      <ProgressSpinner style="width: 44px; height: 44px" stroke-width="4" />
    </div>
    <template v-else-if="loadError">
      <Message severity="error" :closable="false">{{ loadError }}</Message>
      <NuxtLink to="/mot-de-passe-oublie" class="block text-center mt-4 text-primary no-underline text-sm font-medium">Refaire une demande</NuxtLink>
    </template>
    <SetPasswordForm
      v-else
      :email="email"
      label="Enregistrer mon mot de passe"
      :save="password => repository.resetPassword(token, password)"
    />
  </AuthCard>
</template>

<script setup lang="ts">
import SetPasswordForm from '~/components/forms/SetPasswordForm.vue'
import { UserRepository } from '~/repository/user-repository'

useSeoMeta({ title: 'Nouveau mot de passe — Clapiers Volley-Ball', robots: 'noindex' })

// Jeton dans le fragment (#) : ni le serveur, ni le Referer, ni Google Tag
// Manager ne le voient. Lu côté client uniquement (absent au rendu serveur).
let token = ''
const repository = new UserRepository()

const email = ref('')
const loading = ref(true)
const loadError = ref('')

onMounted(async () => {
  token = window.location.hash.slice(1)
  try {
    if (!/^[0-9a-f]{64}$/.test(token)) throw new Error('jeton absent ou mal formé')
    email.value = (await repository.getPasswordReset(token)).email
  } catch (e: any) {
    loadError.value = e?.status === 410
      ? apiErrorMessage(e)
      : "Ce lien n'est pas valide ou a déjà été utilisé."
  } finally {
    loading.value = false
  }
})
</script>
