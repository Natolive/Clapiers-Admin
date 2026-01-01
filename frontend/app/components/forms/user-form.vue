<template>
  <Form :resolver="resolver" :initialValues="initialValues" @submit="onFormSubmit" v-slot="$form" class="flex flex-column gap-4">
    <div class="flex flex-column gap-2">
      <label for="email" class="font-semibold">Email</label>
      <InputText name="email" :disabled="loading" type="email" fluid placeholder="Entrez l'email" />

      <small v-if="$form.email?.invalid" class="p-error text-red-500 text-sm">
        {{ $form.email?.error?.message }}
      </small>
    </div>

    <div class="flex flex-column gap-2">
      <label for="role" class="font-semibold">Rôle</label>
      <RoleSelect
        name="role"
        :disabled="loading"
        :invalid="$form.role?.invalid"
      />

      <small v-if="$form.role?.invalid" class="p-error text-red-500 text-sm">
        {{ $form.role?.error?.message }}
      </small>
    </div>

    <div class="flex flex-column gap-2">
      <label for="password" class="font-semibold">
        {{ user ? 'Nouveau mot de passe (optionnel)' : 'Mot de passe' }}
      </label>
      <Password
        name="password"
        :disabled="loading"
        fluid
        toggleMask
        :feedback="false"
        :placeholder="user ? 'Laisser vide pour ne pas changer' : 'Entrez le mot de passe'"
      />

      <small v-if="$form.password?.invalid" class="p-error text-red-500 text-sm">
        {{ $form.password?.error?.message }}
      </small>
    </div>

    <Button type="submit" label="Enregistrer" class="w-full mt-2" :loading="loading" />
  </Form>
</template>

<script setup lang="ts">
import { zodResolver } from '@primevue/forms/resolvers/zod';
import type { FormSubmitEvent } from '@primevue/forms';
import { z } from 'zod';
import type { AppUser, AppUserRole } from '~/types/entity/AppUser';
import RoleSelect from '~/components/form/select/RoleSelect.vue';

const props = defineProps({
  loading: Boolean,
  user: {
    type: Object as () => AppUser | null,
    default: null
  }
});

const emit = defineEmits<{
  (e: 'submit', payload: { email: string; role: AppUserRole; password: string | null }): void;
}>();

const schema = computed(() => {
  const baseSchema = {
    email: z.string().email({ message: 'Email invalide' }),
    role: z.string().min(1, { message: 'Le rôle est requis' })
  };

  if (props.user) {
    // For update: password is optional
    return z.object({
      ...baseSchema,
      password: z.string().optional().or(z.literal(''))
    });
  } else {
    // For create: password is required
    return z.object({
      ...baseSchema,
      password: z.string().min(6, { message: 'Le mot de passe doit contenir au moins 6 caractères' })
    });
  }
});

type UserFormValues = z.infer<typeof schema.value>;
const resolver = computed(() => zodResolver(schema.value));

const initialValues = computed(() => ({
  email: props.user?.email || '',
  role: props.user?.roles?.[0] || '',
  password: ''
}));

const onFormSubmit = (event: FormSubmitEvent<Record<string, any>>) => {
  if (event.valid) {
    const values = event.values as UserFormValues;
    emit('submit', {
      email: values.email,
      role: values.role as AppUserRole,
      password: values.password || null
    });
  }
};
</script>
