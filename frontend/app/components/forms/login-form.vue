<template>
  <Form :resolver="resolver" @submit="onFormSubmit" v-slot="$form" class="flex flex-column gap-4">
    <div class="flex flex-column gap-2">
      <label for="email" class="font-bold">Adresse e-mail</label>
      <InputText name="email" :disabled="loading" type="text" fluid placeholder="exemple@email.com" />

      <small v-if="$form.email?.invalid" class="p-error text-red-500 text-sm">
        {{ $form.email?.error?.message }}
      </small>
    </div>

    <div class="flex flex-column gap-2">
      <label for="password" class="font-bold">Mot de passe</label>
      <Password name="password" :disabled="loading" :feedback="false" toggleMask fluid placeholder="••••••••" />

      <small v-if="$form.password?.invalid" class="p-error text-red-500 text-sm">
        {{ $form.password?.error?.message }}
      </small>
    </div>

    <Button type="submit" label="Se connecter" icon="pi pi-user" class="w-full mt-2" :loading="loading" />
  </Form>
</template>

<script setup lang="ts">
import { zodResolver } from '@primevue/forms/resolvers/zod';
import { z } from 'zod';
import type {Credentials} from "~/types/custom/Credentials";

defineProps({
  loading: Boolean
});

const emit = defineEmits<{
  (e: 'login-success', payload: Credentials): void;
}>();

const schema = z.object({
  email: z.email({ message: 'Format d\'adresse e-mail invalide' }),
  password: z.string().min(1, { message: 'Le mot de passe doit faire au moins 1 caractère' })
});
const resolver = ref(zodResolver(schema));

const onFormSubmit = ({ valid, values }) => {
  if (valid) {
    emit('login-success', values);
  }
};
</script>