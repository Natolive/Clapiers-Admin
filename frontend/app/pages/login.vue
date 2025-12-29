<template>
  <div class="flex align-items-center justify-content-center min-h-screen bg-surface-50 dark:bg-surface-950 px-4">
    <div class="surface-card p-6 shadow-2 border-round w-full" style="max-width: 450px">

      <div class="text-center mb-5">
        <img src="https://primefaces.org/cdn/primevue/images/logo.svg" alt="Logo" height="50" class="mb-3">
        <h1 class="text-3xl font-medium mb-3">Connexion</h1>
        <span class="text-600 font-medium">Accédez à votre compte</span>
      </div>

      <login-form :loading="isSubmitting" @login-success="handleLogin" />
    </div>
  </div>
</template>

<script setup lang="ts">
import LoginForm from "~/components/forms/login-form.vue";
import type {Credentials} from "~/types/custom/Credentials";
import {useAuthStore} from "~/stores/auth.store";

const isSubmitting = ref<boolean>(false);

const handleLogin = async (credentials: Credentials) => {
  isSubmitting.value = true;

  try {
    await useAuthStore().login(credentials);
    await useRouter().push('/dashboard');
  } catch (e) {
    console.error("Error auth")
  }

  isSubmitting.value = false;
};
</script>