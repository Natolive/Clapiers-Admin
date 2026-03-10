<template>
  <Form :resolver="resolver" :initialValues="initialValues" @submit="onFormSubmit" v-slot="$form" class="flex flex-column gap-4">

    <!-- Prénom / Nom -->
    <div class="form-row-2">
      <div class="flex flex-column gap-2">
        <label class="font-semibold">Prénom</label>
        <InputText name="firstName" :disabled="loading" fluid placeholder="Prénom" />
        <small v-if="$form.firstName?.invalid" class="p-error">{{ $form.firstName?.error?.message }}</small>
      </div>
      <div class="flex flex-column gap-2">
        <label class="font-semibold">Nom</label>
        <InputText name="lastName" :disabled="loading" fluid placeholder="Nom" />
        <small v-if="$form.lastName?.invalid" class="p-error">{{ $form.lastName?.error?.message }}</small>
      </div>
    </div>

    <!-- Email / Téléphone -->
    <div class="form-row-2">
      <div class="flex flex-column gap-2">
        <label class="font-semibold">Email</label>
        <InputText name="email" :disabled="loading" type="email" fluid placeholder="email@exemple.fr" />
        <small v-if="$form.email?.invalid" class="p-error">{{ $form.email?.error?.message }}</small>
      </div>
      <div class="flex flex-column gap-2">
        <label class="font-semibold">Téléphone</label>
        <PhoneInput name="phoneNumber" :disabled="loading" placeholder="Numéro de téléphone" />
        <small v-if="$form.phoneNumber?.invalid" class="p-error">{{ $form.phoneNumber?.error?.message }}</small>
      </div>
    </div>

    <!-- Équipe / N° licence -->
    <div class="form-row-2">
      <div class="flex flex-column gap-2">
        <label class="font-semibold">Équipe</label>
        <SelectInput name="teamId" :options="teams" optionLabel="name" optionValue="id" :disabled="loading" placeholder="Sélectionnez une équipe" />
        <small v-if="$form.teamId?.invalid" class="p-error">{{ $form.teamId?.error?.message }}</small>
      </div>
      <div class="flex flex-column gap-2">
        <label class="font-semibold">N° de licence <span class="text-color-secondary font-normal">(optionnel)</span></label>
        <InputText name="licenseNumber" :disabled="loading" fluid placeholder="Ex : 123456789" />
      </div>
    </div>

    <!-- Sexe / Date de naissance -->
    <div class="form-row-2">
      <div class="flex flex-column gap-2">
        <label class="font-semibold">Sexe</label>
        <SelectInput name="gender" :options="genderOptions" optionLabel="label" optionValue="value" :disabled="loading" placeholder="Sélectionnez" />
        <small v-if="$form.gender?.invalid" class="p-error">{{ $form.gender?.error?.message }}</small>
      </div>
      <div class="flex flex-column gap-2">
        <label class="font-semibold">Date de naissance</label>
        <DatePicker
          v-model="localBirthDate"
          :disabled="loading"
          fluid
          dateFormat="dd/mm/yy"
          placeholder="JJ/MM/AAAA"
          :invalid="!!birthDateError"
        />
        <small v-if="birthDateError" class="p-error">{{ birthDateError }}</small>
      </div>
    </div>

    <!-- Nationalité / Rue -->
    <div class="form-row-2">
      <div class="flex flex-column gap-2">
        <label class="font-semibold">Nationalité</label>
        <SelectInput name="nationality" :options="nationalities" :disabled="loading" filter placeholder="Sélectionnez" />
        <small v-if="$form.nationality?.invalid" class="p-error">{{ $form.nationality?.error?.message }}</small>
      </div>
      <div class="flex flex-column gap-2">
        <label class="font-semibold">Rue</label>
        <InputText name="addressStreet" :disabled="loading" fluid placeholder="Ex : 12 rue des Lilas" />
        <small v-if="$form.addressStreet?.invalid" class="p-error">{{ $form.addressStreet?.error?.message }}</small>
      </div>
    </div>

    <!-- Code postal / Ville -->
    <div class="form-row-2">
      <div class="flex flex-column gap-2">
        <label class="font-semibold">Code postal</label>
        <InputText name="addressZip" :disabled="loading" fluid placeholder="Ex : 34830" />
        <small v-if="$form.addressZip?.invalid" class="p-error">{{ $form.addressZip?.error?.message }}</small>
      </div>
      <div class="flex flex-column gap-2">
        <label class="font-semibold">Ville</label>
        <InputText name="addressCity" :disabled="loading" fluid placeholder="Ex : Clapiers" />
        <small v-if="$form.addressCity?.invalid" class="p-error">{{ $form.addressCity?.error?.message }}</small>
      </div>
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
import { MemberGender, MemberGenderOptions } from '~/types/enum/MemberGender';
import PhoneInput from '~/components/form/input/PhoneInput.vue';
import SelectInput from '~/components/form/input/SelectInput.vue';

const props = defineProps({
  loading: Boolean,
  member: { type: Object as () => Member | null, default: null },
  teams:  { type: Array as () => Team[], default: () => [] },
});

const emit = defineEmits<{
  (e: 'formSubmit', payload: {
    firstName: string; lastName: string; phoneNumber: string; email: string; teamId: number;
    licenseNumber: string | null;
    addressStreet: string; addressZip: string; addressCity: string;
    gender: MemberGender; birthDate: string; nationality: string;
  }): void;
}>();

const genderOptions = MemberGenderOptions;
const { nationalities } = useNationalities();

const localBirthDate = ref<Date | null>(null);
const birthDateError = ref('');
const birthDateSchema = z.date({ required_error: 'La date de naissance est requise', invalid_type_error: 'Date invalide' });

watch(() => props.member, (m) => {
  localBirthDate.value = m?.birthDate ? new Date(m.birthDate + 'T12:00:00') : null;
}, { immediate: true });

const formatLocalDate = (d: Date): string =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

const schema = z.object({
  firstName:     z.string().min(1, { message: 'Le prénom est requis' }),
  lastName:      z.string().min(1, { message: 'Le nom est requis' }),
  phoneNumber:   z.string().min(1, { message: 'Le numéro est requis' }).refine(v => isValidPhoneNumber(v, 'FR'), { message: 'Numéro invalide' }),
  email:         z.string().min(1, { message: "L'email est requis" }).email({ message: 'Email invalide' }),
  teamId:        z.number({ message: "L'équipe est requise" }),
  licenseNumber: z.string().max(50).nullable().optional(),
  addressStreet: z.string().min(1, { message: 'La rue est requise' }).max(255),
  addressZip:    z.string().min(1, { message: 'Le code postal est requis' }).max(10),
  addressCity:   z.string().min(1, { message: 'La ville est requise' }).max(100),
  gender:        z.nativeEnum(MemberGender, { message: 'Le sexe est requis' }),
  nationality:   z.string().min(1, { message: 'La nationalité est requise' }).max(100),
});

type MemberFormValues = z.infer<typeof schema>;
const resolver = ref(zodResolver(schema));

const initialValues = computed(() => ({
  firstName:     props.member?.firstName        ?? '',
  lastName:      props.member?.lastName         ?? '',
  phoneNumber:   props.member?.phoneNumber      ?? '',
  email:         props.member?.email            ?? '',
  teamId:        props.member?.team?.id         ?? null,
  licenseNumber: props.member?.licenseNumber    ?? '',
  addressStreet: props.member?.address?.street  ?? '',
  addressZip:    props.member?.address?.zip     ?? '',
  addressCity:   props.member?.address?.city    ?? '',
  gender:        props.member?.gender           ?? null,
  // undefined = pas d'erreur au chargement, null = DatePicker afficherait une date par défaut
  nationality:   props.member?.nationality      ?? '',
}));

const onFormSubmit = (event: FormSubmitEvent<Record<string, any>>) => {
  if (!event.valid) return;

  const dateResult = birthDateSchema.safeParse(localBirthDate.value);
  birthDateError.value = dateResult.success ? '' : (dateResult.error.issues[0]?.message ?? 'Date invalide');
  if (!dateResult.success) return;

  const v = event.values as MemberFormValues;
  emit('formSubmit', {
    firstName:     v.firstName,
    lastName:      v.lastName,
    phoneNumber:   v.phoneNumber,
    email:         v.email,
    teamId:        v.teamId,
    licenseNumber: v.licenseNumber || null,
    addressStreet: v.addressStreet,
    addressZip:    v.addressZip,
    addressCity:   v.addressCity,
    gender:        v.gender,
    birthDate:     formatLocalDate(localBirthDate.value!),
    nationality:   v.nationality,
  });
};
</script>

<style scoped>
.form-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

@media (max-width: 480px) {
  .form-row-2 { grid-template-columns: 1fr; }
}
</style>
