<template>
  <Form :resolver="resolver" :initialValues="initialValues" @submit="onFormSubmit" v-slot="$form" class="flex flex-column gap-4">
    <div class="flex flex-column gap-2">
      <label for="name" class="font-semibold">Nom de l'équipe</label>
      <InputText name="name" :disabled="loading" type="text" fluid placeholder="Entrez le nom de l'équipe" />

      <small v-if="$form.name?.invalid" class="p-error text-red-500 text-sm">
        {{ $form.name?.error?.message }}
      </small>
    </div>

    <Button type="submit" label="Enregistrer" class="w-full mt-2" :loading="loading" />
  </Form>
</template>

<script setup lang="ts">
import { zodResolver } from '@primevue/forms/resolvers/zod';
import type { FormSubmitEvent } from '@primevue/forms';
import { z } from 'zod';
import type { Team } from '~/types/entity/Team';

const props = defineProps({
  loading: Boolean,
  team: {
    type: Object as () => Team | null,
    default: null
  }
});

const emit = defineEmits<{
  (e: 'submit', payload: { name: string }): void;
}>();

const schema = z.object({
  name: z.string().min(1, { message: 'Le nom de l\'équipe est requis' })
});
type TeamFormValues = z.infer<typeof schema>;
const resolver = ref(zodResolver(schema));

const initialValues = computed(() => ({
  name: props.team?.name || ''
}));

const onFormSubmit = (event: FormSubmitEvent<Record<string, any>>) => {
  if (event.valid) {
    emit('submit', event.values as TeamFormValues);
  }
};
</script>
