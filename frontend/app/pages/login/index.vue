<template>
  <div class="flex align-items-center justify-content-center h-full w-full">

    <div class="surface-card p-6 shadow-2 border-round w-full" style="max-width: 450px">

      <div class="text-center mb-5">
        <img src="https://primefaces.org/cdn/primevue/images/logo.svg" alt="Logo" height="50" class="mb-3">
        <div class="text-900 text-3xl font-medium mb-3">Connexion</div>
        <span class="text-600 font-medium">Veuillez entrer vos identifiants</span>
      </div>

      <div class="flex flex-column gap-4">
        <div class="flex flex-column gap-2">
          <label for="email" class="font-bold">Adresse e-mail</label>
          <InputText id="email" v-model="email" type="text" fluid placeholder="exemple@email.com" />
        </div>

        <div class="flex flex-column gap-2">
          <label for="password" class="font-bold">Mot de passe</label>
          <Password
              id="password"
              v-model="password"
              :feedback="false"
              toggleMask
              fluid
              placeholder="••••••••"
          />
        </div>

        <Button label="Se connecter" icon="pi pi-sign-in" class="w-full mt-2" @click="handleLogin" />
      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';

const email = ref<string | undefined>(undefined);
const password = ref<string | undefined>(undefined);


const handleLogin = () => {
  if (email.value && password.value) {
    alert(`Tentative de connexion pour : ${email.value}`);
  } else {
    alert('Veuillez remplir tous les champs');
  }
};
</script>