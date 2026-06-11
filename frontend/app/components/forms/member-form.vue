<template>
  <Form :resolver="resolver" :initialValues="initialValues" @submit="onFormSubmit" v-slot="$form" class="flex flex-column gap-4">

    <div class="form-section">
      <h4 class="form-section__title">Identité</h4>

      <div class="form-row-2">
        <div class="flex flex-column gap-2">
          <label for="firstName" class="font-semibold">Prénom <span class="text-red-500">*</span></label>
          <InputText id="firstName" name="firstName" :disabled="loading" fluid placeholder="Prénom" />
          <small v-if="$form.firstName?.invalid" class="p-error text-red-500 text-sm">{{ $form.firstName?.error?.message }}</small>
        </div>
        <div class="flex flex-column gap-2">
          <label for="lastName" class="font-semibold">Nom <span class="text-red-500">*</span></label>
          <InputText id="lastName" name="lastName" :disabled="loading" fluid placeholder="Nom" />
          <small v-if="$form.lastName?.invalid" class="p-error text-red-500 text-sm">{{ $form.lastName?.error?.message }}</small>
        </div>
      </div>

      <div class="form-row-2">
        <div class="flex flex-column gap-2">
          <label for="gender" class="font-semibold">Sexe <span class="text-red-500">*</span></label>
          <SelectInput name="gender" inputId="gender" :options="genderOptions" optionLabel="label" optionValue="value" :disabled="loading" placeholder="Sélectionnez" />
          <small v-if="$form.gender?.invalid" class="p-error text-red-500 text-sm">{{ $form.gender?.error?.message }}</small>
        </div>
        <div class="flex flex-column gap-2">
          <label for="birthDate" class="font-semibold">Date de naissance <span class="text-red-500">*</span></label>
          <DatePickerInput name="birthDate" inputId="birthDate" :disabled="loading" :maxDate="today" showIcon placeholder="JJ/MM/AAAA" />
          <small v-if="$form.birthDate?.invalid" class="p-error text-red-500 text-sm">{{ $form.birthDate?.error?.message }}</small>
        </div>
      </div>

      <div class="form-row-2">
        <div class="flex flex-column gap-2">
          <label for="nationality" class="font-semibold">Nationalité <span class="text-red-500">*</span></label>
          <SelectInput name="nationality" inputId="nationality" :options="nationalities" :disabled="loading" filter placeholder="Sélectionnez" />
          <small v-if="$form.nationality?.invalid" class="p-error text-red-500 text-sm">{{ $form.nationality?.error?.message }}</small>
        </div>
      </div>
    </div>

    <div class="form-section">
      <h4 class="form-section__title">Contact</h4>

      <div class="form-row-2">
        <div class="flex flex-column gap-2">
          <label for="email" class="font-semibold">Email <span class="text-red-500">*</span></label>
          <InputText id="email" name="email" :disabled="loading" type="email" fluid placeholder="email@exemple.fr" />
          <small v-if="$form.email?.invalid" class="p-error text-red-500 text-sm">{{ $form.email?.error?.message }}</small>
        </div>
        <div class="flex flex-column gap-2">
          <label for="phoneNumber" class="font-semibold">Téléphone <span class="text-red-500">*</span></label>
          <PhoneInput name="phoneNumber" inputId="phoneNumber" :disabled="loading" placeholder="Numéro de téléphone" />
          <small v-if="$form.phoneNumber?.invalid" class="p-error text-red-500 text-sm">{{ $form.phoneNumber?.error?.message }}</small>
        </div>
      </div>
    </div>

    <div class="form-section">
      <h4 class="form-section__title">Adresse</h4>

      <div class="flex flex-column gap-2">
        <label for="addressStreet" class="font-semibold">Rue <span class="text-red-500">*</span></label>
        <InputText id="addressStreet" name="addressStreet" :disabled="loading" fluid placeholder="Ex : 12 rue des Lilas" />
        <small v-if="$form.addressStreet?.invalid" class="p-error text-red-500 text-sm">{{ $form.addressStreet?.error?.message }}</small>
      </div>

      <div class="form-row-2">
        <div class="flex flex-column gap-2">
          <label for="addressZip" class="font-semibold">Code postal <span class="text-red-500">*</span></label>
          <InputText id="addressZip" name="addressZip" :disabled="loading" fluid placeholder="Ex : 34830" />
          <small v-if="$form.addressZip?.invalid" class="p-error text-red-500 text-sm">{{ $form.addressZip?.error?.message }}</small>
        </div>
        <div class="flex flex-column gap-2">
          <label for="addressCity" class="font-semibold">Ville <span class="text-red-500">*</span></label>
          <InputText id="addressCity" name="addressCity" :disabled="loading" fluid placeholder="Ex : Clapiers" />
          <small v-if="$form.addressCity?.invalid" class="p-error text-red-500 text-sm">{{ $form.addressCity?.error?.message }}</small>
        </div>
      </div>
    </div>

    <div class="form-section">
      <h4 class="form-section__title">Club</h4>

      <div class="form-row-2">
        <div class="flex flex-column gap-2">
          <label for="teamIds" class="font-semibold">Équipes <span class="text-red-500">*</span></label>
          <FormField v-slot="$field" name="teamIds">
            <MultiSelect
              :modelValue="$field.value ?? []"
              :options="teams"
              optionLabel="name"
              optionValue="id"
              :maxSelectedLabels="2"
              selectedItemsLabel="{0} équipes sélectionnées"
              filter
              filterPlaceholder="Rechercher une équipe"
              inputId="teamIds"
              placeholder="Sélectionnez les équipes"
              :disabled="loading"
              :invalid="$field.invalid"
              fluid
              @update:modelValue="onTeamsChange($field, $event)"
            />
          </FormField>
          <small v-if="$form.teamIds?.invalid" class="p-error text-red-500 text-sm">{{ $form.teamIds?.error?.message }}</small>
        </div>
        <div class="flex flex-column gap-2">
          <label for="licenseNumber" class="font-semibold">N° de licence <span class="text-color-secondary font-normal">(optionnel)</span></label>
          <InputText id="licenseNumber" name="licenseNumber" :disabled="loading" fluid placeholder="Ex : 123456789" />
        </div>
      </div>
    </div>

    <div class="form-actions">
      <Button v-if="showCancel" type="button" label="Annuler" severity="secondary" outlined :disabled="loading" @click="emit('cancel')" />
      <Button type="submit" label="Enregistrer" :loading="loading" />
    </div>
  </Form>
</template>

<script setup lang="ts">
import { zodResolver } from '@primevue/forms/resolvers/zod';
import { FormField } from '@primevue/forms';
import type { FormSubmitEvent } from '@primevue/forms';
import { z } from 'zod';
import { isValidPhoneNumber } from 'libphonenumber-js';
import type { Member } from '~/types/entity/Member';
import type { Team } from '~/types/entity/Team';
import { MemberGender, MemberGenderOptions } from '~/types/enum/MemberGender';
import PhoneInput from '~/components/form/input/PhoneInput.vue';
import SelectInput from '~/components/form/input/SelectInput.vue';
import DatePickerInput from '~/components/form/input/DatePickerInput.vue';

const props = defineProps({
  loading: Boolean,
  showCancel: Boolean,
  member: { type: Object as () => Member | null, default: null },
  teams:  { type: Array as () => Team[], default: () => [] },
});

const emit = defineEmits<{
  (e: 'formSubmit', payload: {
    firstName: string; lastName: string; phoneNumber: string; email: string; teamIds: number[];
    licenseNumber: string | null;
    addressStreet: string; addressZip: string; addressCity: string;
    gender: MemberGender; birthDate: string; nationality: string;
  }): void;
  (e: 'cancel'): void;
}>();

// Le type du slot FormField n'expose pas onChange (présent au runtime)
const onTeamsChange = (field: any, value: number[]) => field.onChange({ value });

const genderOptions = MemberGenderOptions;
const { nationalities } = useNationalities();
const today = new Date();

const formatLocalDate = (d: Date): string =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

const schema = z.object({
  firstName:     z.string().min(1, { message: 'Le prénom est requis' }),
  lastName:      z.string().min(1, { message: 'Le nom est requis' }),
  phoneNumber:   z.string().min(1, { message: 'Le numéro est requis' }).refine(v => isValidPhoneNumber(v, 'FR'), { message: 'Numéro invalide' }),
  email:         z.string().min(1, { message: "L'email est requis" }).email({ message: 'Email invalide' }),
  teamIds:       z.array(z.number()).min(1, { message: 'Au moins une équipe est requise' }),
  licenseNumber: z.string().max(50).nullable().optional(),
  addressStreet: z.string().min(1, { message: 'La rue est requise' }).max(255),
  addressZip:    z.string().min(1, { message: 'Le code postal est requis' }).max(10),
  addressCity:   z.string().min(1, { message: 'La ville est requise' }).max(100),
  gender:        z.nativeEnum(MemberGender, { message: 'Le sexe est requis' }),
  birthDate:     z.date({ message: 'La date de naissance est requise' }),
  nationality:   z.string().min(1, { message: 'La nationalité est requise' }).max(100),
});

type MemberFormValues = z.infer<typeof schema>;
const resolver = ref(zodResolver(schema));

const initialValues = computed(() => ({
  firstName:     props.member?.firstName        ?? '',
  lastName:      props.member?.lastName         ?? '',
  phoneNumber:   props.member?.phoneNumber      ?? '',
  email:         props.member?.email            ?? '',
  teamIds:       props.member?.teams?.map(t => t.id) ?? [],
  licenseNumber: props.member?.licenseNumber    ?? '',
  addressStreet: props.member?.address?.street  ?? '',
  addressZip:    props.member?.address?.zip     ?? '',
  addressCity:   props.member?.address?.city    ?? '',
  gender:        props.member?.gender           ?? null,
  // undefined = pas d'erreur au chargement, null = DatePicker afficherait une date par défaut
  birthDate:     props.member?.birthDate ? new Date(props.member.birthDate + 'T12:00:00') : undefined,
  nationality:   props.member?.nationality      ?? '',
}));

const onFormSubmit = (event: FormSubmitEvent<Record<string, any>>) => {
  if (!event.valid) return;

  const v = event.values as MemberFormValues;
  emit('formSubmit', {
    firstName:     v.firstName,
    lastName:      v.lastName,
    phoneNumber:   v.phoneNumber,
    email:         v.email,
    teamIds:       v.teamIds,
    licenseNumber: v.licenseNumber || null,
    addressStreet: v.addressStreet,
    addressZip:    v.addressZip,
    addressCity:   v.addressCity,
    gender:        v.gender,
    birthDate:     formatLocalDate(v.birthDate),
    nationality:   v.nationality,
  });
};
</script>

<style scoped>
.form-section {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-section__title {
  margin: 0;
  padding-bottom: 0.375rem;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--p-text-muted-color);
  border-bottom: 1px solid var(--p-surface-border);
}

.form-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 0.25rem;
}

@media (max-width: 640px) {
  .form-row-2 { grid-template-columns: 1fr; }

  .form-actions {
    flex-direction: column-reverse;
  }

  .form-actions :deep(.p-button) {
    width: 100%;
  }
}
</style>
