<template>
  <Form :resolver="resolver" :initialValues="initialValues" @submit="onFormSubmit" v-slot="$form" class="flex flex-column gap-4">
    <div class="flex flex-column gap-2">
      <label for="name" class="font-semibold">Nom de l'équipe</label>
      <InputText name="name" :disabled="loading" type="text" fluid placeholder="Entrez le nom de l'équipe" />

      <small v-if="$form.name?.invalid" class="p-error text-red-500 text-sm">
        {{ $form.name?.error?.message }}
      </small>
    </div>

    <div class="flex flex-column gap-2">
      <label for="userIds" class="font-semibold">
        Coachs <span class="text-color-secondary font-normal">(gèrent cette équipe)</span>
      </label>
      <FormField v-slot="$field" name="userIds">
        <MultiSelect
          :modelValue="$field.value ?? []"
          :options="users"
          optionLabel="email"
          optionValue="id"
          :maxSelectedLabels="3"
          selectedItemsLabel="{0} coachs sélectionnés"
          filter
          filterPlaceholder="Rechercher un utilisateur"
          inputId="userIds"
          placeholder="Aucun coach"
          :disabled="loading"
          fluid
          @update:modelValue="onCoachesChange($field, $event)"
        />
      </FormField>
    </div>

    <Button type="submit" label="Enregistrer" class="w-full mt-2" :loading="loading" />
  </Form>
</template>

<script setup lang="ts">
import { zodResolver } from '@primevue/forms/resolvers/zod';
import type { FormSubmitEvent } from '@primevue/forms';
import { z } from 'zod';
import type { Team } from '~/types/entity/Team';
import type { AppUser } from '~/types/entity/AppUser';

const props = defineProps({
  loading: Boolean,
  team: {
    type: Object as () => Team | null,
    default: null
  },
  users: {
    type: Array as () => AppUser[],
    default: () => []
  }
});

const emit = defineEmits<{
  (e: 'submit', payload: { name: string; userIds: number[] }): void;
}>();

const schema = z.object({
  name: z.string().min(1, { message: 'Le nom de l\'équipe est requis' }),
  userIds: z.array(z.number()).optional()
});
type TeamFormValues = z.infer<typeof schema>;
const resolver = ref(zodResolver(schema));

const initialValues = computed(() => ({
  name: props.team?.name || '',
  userIds: props.team?.coaches?.map(c => c.id) ?? []
}));

// PrimeVue Forms ne rebinde pas un MultiSelect contrôlé sans un coup de pouce.
const onCoachesChange = (field: any, value: number[]) => {
  field.value = value;
};

const onFormSubmit = (event: FormSubmitEvent<Record<string, any>>) => {
  if (event.valid) {
    const values = event.values as TeamFormValues;
    emit('submit', { name: values.name, userIds: values.userIds ?? [] });
  }
};
</script>
