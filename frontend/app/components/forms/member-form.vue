<template>
  <Form :resolver="resolver" :initialValues="initialValues" @submit="onFormSubmit" v-slot="$form" class="flex flex-column gap-4">
    <div class="flex flex-column gap-2">
      <label for="firstName" class="font-semibold">Prénom</label>
      <InputText name="firstName" :disabled="loading" type="text" fluid placeholder="Entrez le prénom" />

      <small v-if="$form.firstName?.invalid" class="p-error text-red-500 text-sm">
        {{ $form.firstName?.error?.message }}
      </small>
    </div>

    <div class="flex flex-column gap-2">
      <label for="lastName" class="font-semibold">Nom</label>
      <InputText name="lastName" :disabled="loading" type="text" fluid placeholder="Entrez le nom" />

      <small v-if="$form.lastName?.invalid" class="p-error text-red-500 text-sm">
        {{ $form.lastName?.error?.message }}
      </small>
    </div>

    <div class="flex flex-column gap-2">
      <label for="email" class="font-semibold">Email</label>
      <InputText name="email" :disabled="loading" type="email" fluid placeholder="Entrez l'email" />

      <small v-if="$form.email?.invalid" class="p-error text-red-500 text-sm">
        {{ $form.email?.error?.message }}
      </small>
    </div>

    <div class="flex flex-column gap-2">
      <label for="phoneNumber" class="font-semibold">Téléphone</label>
      <PhoneInput name="phoneNumber" :disabled="loading" placeholder="Entrez le numéro de téléphone" />

      <small v-if="$form.phoneNumber?.invalid" class="p-error text-red-500 text-sm">
        {{ $form.phoneNumber?.error?.message }}
      </small>
    </div>

    <div class="flex flex-column gap-2">
      <label for="teamId" class="font-semibold">Équipe</label>
      <Select
        name="teamId"
        :options="teams"
        optionLabel="name"
        optionValue="id"
        :disabled="loading"
        fluid
        placeholder="Sélectionnez une équipe"
      />

      <small v-if="$form.teamId?.invalid" class="p-error text-red-500 text-sm">
        {{ $form.teamId?.error?.message }}
      </small>
    </div>

    <Button type="submit" label="Enregistrer" class="w-full mt-2" :loading="loading" />
  </Form>
</template>

<script setup lang="ts">
import { zodResolver } from '@primevue/forms/resolvers/zod';
import type { FormSubmitEvent } from '@primevue/forms';
import { z } from 'zod';
import { isValidPhoneNumber } from 'libphonenumber-js';
import type { Member } from '~/types/entity/Member';
import type { Team } from '~/types/entity/Team';
import PhoneInput from '~/components/form/input/PhoneInput.vue';

const props = defineProps({
  loading: Boolean,
  member: {
    type: Object as () => Member | null,
    default: null
  },
  teams: {
    type: Array as () => Team[],
    default: () => []
  }
});

const emit = defineEmits<{
  (e: 'submit', payload: { firstName: string; lastName: string; phoneNumber: string; email: string; teamId: number }): void;
}>();

const schema = z.object({
  firstName: z.string().min(1, { message: 'Le prénom est requis' }),
  lastName: z.string().min(1, { message: 'Le nom est requis' }),
  phoneNumber: z.string().min(1, { message: 'Le numéro de téléphone est requis' }).refine((val) => isValidPhoneNumber(val, 'FR'), { message: 'Le numéro de téléphone doit être valide' }),
  email: z.string().min(1, { message: 'L\'email est requis' }).email({ message: 'L\'email doit être valide' }),
  teamId: z.number({ message: 'L\'équipe est requise' })
});
type MemberFormValues = z.infer<typeof schema>;
const resolver = ref(zodResolver(schema));

const initialValues = computed(() => ({
  firstName: props.member?.firstName || '',
  lastName: props.member?.lastName || '',
  phoneNumber: props.member?.phoneNumber || '',
  email: props.member?.email || '',
  teamId: props.member?.team?.id || null
}));

const onFormSubmit = (event: FormSubmitEvent<Record<string, any>>) => {
  if (event.valid) {
    emit('submit', event.values as MemberFormValues);
  }
};
</script>
