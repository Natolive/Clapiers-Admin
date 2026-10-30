<template>
  <PublicPage label="Espace club" :title="title" :subtitle="subtitle">
    <div class="invite-card">
      <div v-if="loading" class="flex justify-content-center">
        <ProgressSpinner style="width: 44px; height: 44px" stroke-width="4" />
      </div>

      <Message v-else-if="loadError" severity="error" :closable="false">{{ loadError }}</Message>

      <form v-else class="flex flex-column gap-3" @submit.prevent="submit">
        <div class="flex flex-column gap-2">
          <label for="invite-email" class="font-semibold">E-mail de connexion</label>
          <InputText id="invite-email" :modelValue="invitation?.email" disabled fluid />
        </div>
        <div class="flex flex-column gap-2">
          <label for="invite-password" class="font-semibold">Mot de passe</label>
          <Password
            v-model="password"
            inputId="invite-password"
            toggleMask
            fluid
            :feedback="false"
            autocomplete="new-password"
            placeholder="8 caractères minimum"
          />
        </div>
        <div class="flex flex-column gap-2">
          <label for="invite-confirm" class="font-semibold">Confirmation</label>
          <Password
            v-model="confirm"
            inputId="invite-confirm"
            toggleMask
            fluid
            :feedback="false"
            autocomplete="new-password"
            :invalid="!!confirm && confirm !== password"
          />
        </div>

        <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

        <Button
          type="submit"
          label="Créer mon mot de passe"
          icon="pi pi-check"
          :loading="saving"
          :disabled="password.length < 8 || password !== confirm"
        />
      </form>
    </div>
  </PublicPage>
</template>

<script setup lang="ts">
import { UserRepository, type InvitationView } from '~/repository/user-repository'

definePageMeta({ layout: 'public' })
useSeoMeta({ title: 'Activer mon compte — Clapiers Volley-Ball', robots: 'noindex' })

const token = useRoute().params.token as string
const repository = new UserRepository()

const invitation = ref<InvitationView | null>(null)
const loading = ref(true)
const loadError = ref('')
const password = ref('')
const confirm = ref('')
const saving = ref(false)
const error = ref('')

const title = computed(() => invitation.value?.firstName ? `Bienvenue ${invitation.value.firstName}` : 'Bienvenue')
const subtitle = 'Choisissez votre mot de passe pour accéder à la gestion de votre équipe.'

onMounted(async () => {
  try {
    invitation.value = await repository.getInvitation(token)
  } catch (e: any) {
    loadError.value = e?.status === 410
      ? apiErrorMessage(e)
      : "Ce lien d'invitation n'est pas valide ou a déjà été utilisé."
  } finally {
    loading.value = false
  }
})

// Mot de passe choisi → connexion directe, sans repasser par /login.
const submit = async () => {
  saving.value = true
  error.value = ''
  try {
    const { email } = await repository.acceptInvitation(token, password.value)
    await useAuthStore().login({ email, password: password.value })
    await navigateTo('/dashboard')
  } catch (e: any) {
    error.value = apiErrorMessage(e)
    saving.value = false
  }
}
</script>

<style scoped>
.invite-card {
  max-width: 440px;
  margin: 0 auto;
  background: var(--p-content-background, #fff);
  border-radius: 16px;
  padding: 2rem;
  box-shadow: 0 12px 40px rgba(15, 26, 46, 0.12);
}
</style>
