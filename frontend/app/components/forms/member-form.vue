<template>
  <Form ref="form" :resolver="resolver" :initialValues="initialValues" @submit="onFormSubmit" v-slot="$form" class="flex flex-column gap-4">

    <!-- Étapes : seulement à la création, là où il y a beaucoup à saisir d'un coup -->
    <ol v-if="stepped" class="steps">
      <li v-for="(s, i) in STEPS" :key="s.key" :class="{ done: i < stepIndex, active: i === stepIndex }">
        <span class="steps__dot">{{ i + 1 }}</span>
        <span class="steps__label">{{ s.label }}</span>
      </li>
    </ol>

    <Message v-if="stepError" severity="error" :closable="false">{{ stepError }}</Message>

    <div v-show="showStep('identity')" class="form-section">
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

    <div v-show="showStep('contact')" class="form-section">
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

    <div v-show="showStep('contact')" class="form-section">
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

    <div v-show="showStep('club')" class="form-section">
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

    <div v-show="showStep('license')" class="form-section">
      <h4 class="form-section__title">Licence FSGT</h4>

      <div class="flex align-items-center gap-2">
        <Checkbox v-model="withLicense" inputId="withLicense" binary :disabled="loading" @update:modelValue="onWithLicense" />
        <label for="withLicense">Créer la demande de licence pour la saison</label>
        <Select
          v-model="licenseSeason"
          :options="seasons"
          :disabled="loading || !withLicense"
          class="w-8rem"
        />
      </div>
      <small class="text-color-secondary">
        Validée d'emblée : sans licence sur la saison, la fiche n'apparaît dans aucune liste.
      </small>

      <template v-if="withLicense">
        <div class="flex flex-column gap-2">
          <label for="tier" class="font-semibold">Tarif <span class="text-color-secondary font-normal">(requis pour l'e-mail)</span></label>
          <Select
            v-model="selectedTier"
            inputId="tier"
            :options="tiers"
            option-label="label"
            :loading="loadingTiers"
            :disabled="loading"
            show-clear
            placeholder="Choisir un tarif"
            fluid
          >
            <template #option="{ option }">{{ option.label }} — {{ formatAmount(option.amount) }}</template>
            <template #value="{ value }">
              <span v-if="value">{{ value.label }} — {{ formatAmount(value.amount) }}</span>
              <span v-else>Choisir un tarif</span>
            </template>
          </Select>
          <small v-if="!loadingTiers && tiers.length === 0" class="text-color-secondary">
            Aucun tarif HelloAsso trouvé — la licence sera créée sans montant.
          </small>
        </div>

        <div class="flex align-items-center gap-2">
          <Checkbox v-model="sendPaymentEmail" inputId="sendPaymentEmail" binary :disabled="loading" />
          <label for="sendPaymentEmail">Envoyer l'e-mail « licence validée » avec le lien de paiement</label>
        </div>
        <small class="text-color-secondary">
          Décoché : rien n'est envoyé. Le lien reste renvoyable depuis les demandes de licence.
        </small>
      </template>
    </div>

    <div class="form-actions">
      <Button v-if="showCancel" type="button" label="Annuler" severity="secondary" outlined :disabled="loading" @click="emit('cancel')" />
      <span class="form-actions__spacer" />
      <Button v-if="stepped && stepIndex > 0" type="button" label="Précédent" severity="secondary" outlined :disabled="loading" @click="prev" />
      <Button v-if="stepped && !isLastStep" type="button" label="Suivant" icon="pi pi-arrow-right" icon-pos="right" @click="next" />
      <Button v-else type="submit" label="Enregistrer" :loading="loading" />
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
import { LicenseAdminRepository, type LicenseTier } from '~/repository/license-admin-repository';

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
    license?: { season: string; helloAssoTierId: number | null; amount: number | null; sendPaymentEmail: boolean };
  }): void;
  (e: 'cancel'): void;
}>();

// ── Étapes ────────────────────────────────────────────────────────────────
// Seulement à la création : la modification ouvre une fiche déjà remplie, et
// la découper en 4 écrans ne ferait qu'ajouter des clics.
const STEPS = [
  { key: 'identity', label: 'Identité', fields: ['firstName', 'lastName', 'gender', 'birthDate', 'nationality'] },
  { key: 'contact', label: 'Coordonnées', fields: ['email', 'phoneNumber', 'addressStreet', 'addressZip', 'addressCity'] },
  { key: 'club', label: 'Club', fields: ['teamIds'] },
  { key: 'license', label: 'Licence', fields: [] },
] as const;

const form = ref<any>(null);
const stepped = computed(() => !props.member);
const stepIndex = ref(0);
const stepError = ref('');
const isLastStep = computed(() => stepIndex.value === STEPS.length - 1);
const showStep = (key: string) => !stepped.value || STEPS[stepIndex.value]?.key === key;

const prev = () => {
  stepError.value = '';
  stepIndex.value--;
};

const next = async () => {
  const fields = STEPS[stepIndex.value]?.fields ?? [];
  await Promise.all(fields.map((f) => form.value?.validate(f)));
  const invalid = fields.find((f) => form.value?.states?.[f]?.invalid);
  if (invalid) {
    stepError.value = form.value?.states?.[invalid]?.error?.message || 'Veuillez corriger les champs en rouge.';
    return;
  }

  stepError.value = '';
  stepIndex.value++;
  if (STEPS[stepIndex.value]?.key === 'license') loadTiers();
};

// ── Licence de la saison ──────────────────────────────────────────────────
// Hors du <Form> : rien à valider par zod, et le tarif est un objet (id +
// montant figé) que le back veut décomposé.
const { selected: selectedSeason, seasons, load: loadSeasons } = useSeasonFilter();
const withLicense = ref(!props.member);
const licenseSeason = ref('');
const sendPaymentEmail = ref(false);
const tiers = ref<LicenseTier[]>([]);
const selectedTier = ref<LicenseTier | null>(null);
const loadingTiers = ref(false);
let tiersRequested = false;

const formatAmount = (cents: number) => (cents / 100).toFixed(2).replace('.', ',') + ' €';

const loadTiers = async () => {
  if (tiersRequested) return;
  tiersRequested = true;
  loadingTiers.value = true;
  try {
    tiers.value = await new LicenseAdminRepository().getTiers();
  } catch {
    tiers.value = [];
  } finally {
    loadingTiers.value = false;
  }
};

const onWithLicense = (on: boolean) => { if (on) loadTiers(); };

// Saison proposée : celle du filtre de la liste, sinon la courante côté back
// (chaîne vide = « laisse le serveur décider »).
onMounted(async () => {
  await loadSeasons();
  licenseSeason.value = selectedSeason.value;
});

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
  // Une erreur peut venir d'une étape masquée : y ramener, sinon le bouton
  // « Enregistrer » ne fait rien et rien n'explique pourquoi.
  if (!event.valid) {
    const step = STEPS.findIndex((s) => s.fields.some((f) => (event as any).states?.[f]?.invalid));
    if (stepped.value && step >= 0) stepIndex.value = step;
    stepError.value = 'Veuillez corriger les champs en rouge.';
    return;
  }

  // Le mail annonce un montant : sans tarif il annoncerait 0 € (le back refuse).
  if (withLicense.value && sendPaymentEmail.value && !selectedTier.value) {
    stepError.value = "Choisissez un tarif pour envoyer le lien de paiement.";
    return;
  }
  stepError.value = '';

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
    ...(withLicense.value ? {
      license: {
        season: licenseSeason.value,
        helloAssoTierId: selectedTier.value?.id ?? null,
        amount: selectedTier.value?.amount ?? null,
        sendPaymentEmail: sendPaymentEmail.value,
      },
    } : {}),
  });
};
</script>

<style scoped>
.steps {
  display: flex;
  gap: 0.5rem;
  list-style: none;
  margin: 0;
  padding: 0;
  flex-wrap: wrap;
}

.steps li {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
}

.steps li + li::before {
  content: '';
  width: 1rem;
  height: 1px;
  background: var(--p-surface-border);
}

.steps__dot {
  display: grid;
  place-items: center;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 50%;
  border: 1px solid var(--p-surface-border);
  font-size: 0.75rem;
}

.steps li.active { color: var(--p-primary-color); font-weight: 600; }
.steps li.active .steps__dot { border-color: var(--p-primary-color); }
.steps li.done .steps__dot {
  background: var(--p-primary-color);
  border-color: var(--p-primary-color);
  color: var(--p-primary-contrast-color);
}

.form-actions__spacer { flex: 1; }

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
