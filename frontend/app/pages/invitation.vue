<template>
  <AuthCard :title="title" subtitle="Choisissez votre mot de passe pour accéder à la gestion de votre équipe.">
    <div v-if="loading" class="flex justify-content-center">
      <ProgressSpinner style="width: 44px; height: 44px" stroke-width="4" />
    </div>
    <Message v-else-if="loadError" severity="error" :closable="false">{{ loadError }}</Message>
    <SetPasswordForm
      v-else
      :email="invitation!.email"
      label="Créer mon mot de passe"
      :save="password => repository.acceptInvitation(token, password)"
    />
  </AuthCard>
</template>

<script setup lang="ts">
import SetPasswordForm from '~/components/forms/SetPasswordForm.vue'
import { UserRepository, type InvitationView } from '~/repository/user-repository'

useSeoMeta({ title: 'Activer mon compte — Clapiers Volley-Ball', robots: 'noindex' })

// Jeton dans le fragment (#) : ni le serveur, ni le Referer, ni Google Tag
// Manager ne le voient. Lu côté client uniquement (absent au rendu serveur).
let token = ''
const repository = new UserRepository()

const invitation = ref<InvitationView | null>(null)
const loading = ref(true)
const loadError = ref('')

const title = computed(() => invitation.value?.firstName ? `Bienvenue ${invitation.value.firstName}` : 'Bienvenue')

onMounted(async () => {
  token = window.location.hash.slice(1)
  try {
    if (!/^[0-9a-f]{64}$/.test(token)) throw new Error('jeton absent ou mal formé')
    invitation.value = await repository.getInvitation(token)
  } catch (e: any) {
    loadError.value = e?.status === 410
      ? apiErrorMessage(e)
      : "Ce lien d'invitation n'est pas valide ou a déjà été utilisé."
  } finally {
    loading.value = false
  }
})
</script>
