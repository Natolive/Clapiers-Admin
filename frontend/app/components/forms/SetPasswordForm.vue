<template>
  <form class="flex flex-column gap-3" @submit.prevent="submit">
    <div class="flex flex-column gap-2">
      <label for="set-password-email" class="font-bold">E-mail de connexion</label>
      <!-- readonly et non disabled : un gestionnaire de mots de passe ignore un
           champ désactivé et ne saurait pas à quel compte rattacher le mot de passe. -->
      <InputText id="set-password-email" :modelValue="email" readonly fluid autocomplete="username" class="opacity-60" />
    </div>
    <div class="flex flex-column gap-2">
      <label for="set-password" class="font-bold">Mot de passe</label>
      <Password
        v-model="password"
        inputId="set-password"
        toggleMask
        fluid
        :feedback="false"
        :inputProps="{ autocomplete: 'new-password' }"
        placeholder="8 caractères minimum"
      />
    </div>
    <div class="flex flex-column gap-2">
      <label for="set-password-confirm" class="font-bold">Confirmation</label>
      <Password
        v-model="confirm"
        inputId="set-password-confirm"
        toggleMask
        fluid
        :feedback="false"
        :inputProps="{ autocomplete: 'new-password' }"
        :invalid="!!confirm && confirm !== password"
      />
    </div>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

    <Button
      type="submit"
      class="w-full mt-2"
      :label="label"
      icon="pi pi-check"
      :loading="saving"
      :disabled="password.length < 8 || password !== confirm"
    />
  </form>
</template>

<script setup lang="ts">
// Choix d'un mot de passe par lien (invitation, mot de passe oublié) : envoie
// le mot de passe, puis connecte directement le compte.
const props = defineProps<{
  email: string;
  label: string;
  /** Appel API qui pose le mot de passe ; renvoie l'e-mail du compte. */
  save: (password: string) => Promise<{ email: string }>;
}>();

const password = ref('');
const confirm = ref('');
const saving = ref(false);
const error = ref('');

const submit = async () => {
  saving.value = true;
  error.value = '';
  try {
    const { email } = await props.save(password.value);
    await useAuthStore().login({ email, password: password.value });
    await navigateTo('/dashboard');
  } catch (e: any) {
    error.value = apiErrorMessage(e);
    saving.value = false;
  }
};
</script>
