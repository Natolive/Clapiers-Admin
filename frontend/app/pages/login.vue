<template>
  <AuthCard title="Connexion" subtitle="Accédez à votre compte">
    <login-form :loading="isSubmitting" @login-success="handleLogin" />
    <div class="text-center mt-4">
      <NuxtLink to="/mot-de-passe-oublie" class="text-primary no-underline text-sm font-medium">Mot de passe oublié ?</NuxtLink>
    </div>
  </AuthCard>
</template>

<script setup lang="ts">
import LoginForm from "~/components/forms/login-form.vue";
import type {Credentials} from "~/types/custom/Credentials";
import {useAuthStore} from "~/stores/auth.store";

useHead({ title: 'Connexion - CVB' })

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