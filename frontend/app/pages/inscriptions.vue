<template>
  <div class="inscription-page">
    <div class="container">
      <header class="page-head">
        <span class="section-label">Adhésion<template v-if="season"> · Saison {{ season }}</template></span>
        <h1>Demande de licence</h1>
        <p>
          Remplissez le formulaire en quelques étapes. Votre demande sera vérifiée
          par un responsable du club&nbsp;; vous recevrez ensuite un lien par
          e-mail pour régler votre licence en ligne.
        </p>
      </header>

      <!-- Confirmation finale -->
      <div v-if="done" class="card success">
        <i class="pi pi-check-circle"></i>
        <div>
          <h3>Demande envoyée&nbsp;!</h3>
          <p>
            Votre demande est en attente de validation. Vous serez recontacté(e)
            par e-mail dès qu'elle aura été vérifiée.
          </p>
          <NuxtLink to="/" class="back-link">← Retour à l'accueil</NuxtLink>
        </div>
      </div>

      <Form
        v-else
        ref="form"
        v-slot="$form"
        :resolver="resolver"
        :initial-values="initialValues"
        :validate-on-value-update="false"
        :validate-on-blur="false"
        class="card"
        @submit="onSubmit"
      >
        <!-- Indicateur d'étapes -->
        <ol class="steps">
          <li v-for="(s, i) in steps" :key="s" :class="{ done: i < stepIndex, active: i === stepIndex }">
            <span class="dot">{{ i + 1 }}</span>
            <span class="step-label">{{ stepLabels[s] }}</span>
          </li>
        </ol>

        <Message v-if="stepError" severity="error" :closable="false" class="form-error">{{ stepError }}</Message>

        <!-- Étape : identité -->
        <section v-show="currentStep === 'identity'" class="fields">
          <div class="form-row">
            <div class="form-group">
              <label for="firstName">Prénom</label>
              <InputText id="firstName" name="firstName" fluid autocomplete="given-name" />
              <small v-if="$form.firstName?.invalid" class="field-error">{{ $form.firstName.error?.message }}</small>
            </div>
            <div class="form-group">
              <label for="lastName">Nom</label>
              <InputText id="lastName" name="lastName" fluid autocomplete="family-name" />
              <small v-if="$form.lastName?.invalid" class="field-error">{{ $form.lastName.error?.message }}</small>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="email">Email</label>
              <InputText id="email" name="email" type="email" fluid autocomplete="email" />
              <small v-if="$form.email?.invalid" class="field-error">{{ $form.email.error?.message }}</small>
            </div>
            <div class="form-group">
              <label for="phoneNumber">Téléphone</label>
              <PhoneInput name="phoneNumber" input-id="phoneNumber" placeholder="Numéro de téléphone" />
              <small v-if="$form.phoneNumber?.invalid" class="field-error">{{ $form.phoneNumber.error?.message }}</small>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="gender">Genre</label>
              <SelectInput name="gender" input-id="gender" :options="genderOptions" option-label="label" option-value="value" placeholder="Sélectionnez" />
              <small v-if="$form.gender?.invalid" class="field-error">{{ $form.gender.error?.message }}</small>
            </div>
            <div class="form-group">
              <label for="birthDate">Date de naissance</label>
              <InputText id="birthDate" name="birthDate" type="date" :max="todayInput" fluid />
              <small v-if="$form.birthDate?.invalid" class="field-error">{{ $form.birthDate.error?.message }}</small>
            </div>
          </div>

          <div class="form-group">
            <label for="nationality">Nationalité</label>
            <SelectInput name="nationality" input-id="nationality" :options="nationalities" filter placeholder="Sélectionnez" />
            <small v-if="$form.nationality?.invalid" class="field-error">{{ $form.nationality.error?.message }}</small>
          </div>

          <div class="form-group">
            <label for="addressStreet">Adresse</label>
            <InputText id="addressStreet" name="addressStreet" fluid autocomplete="street-address" placeholder="N° et rue" />
            <small v-if="$form.addressStreet?.invalid" class="field-error">{{ $form.addressStreet.error?.message }}</small>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="addressZip">Code postal</label>
              <InputText id="addressZip" name="addressZip" fluid autocomplete="postal-code" />
              <small v-if="$form.addressZip?.invalid" class="field-error">{{ $form.addressZip.error?.message }}</small>
            </div>
            <div class="form-group">
              <label for="addressCity">Ville</label>
              <InputText id="addressCity" name="addressCity" fluid autocomplete="address-level2" />
              <small v-if="$form.addressCity?.invalid" class="field-error">{{ $form.addressCity.error?.message }}</small>
            </div>
          </div>

          <div class="form-group">
            <label for="licenseNumber">N° de licence <span class="optional">(si renouvellement)</span></label>
            <InputText id="licenseNumber" name="licenseNumber" fluid />
          </div>
        </section>

        <!-- Étape : représentant légal (mineur) -->
        <section v-show="currentStep === 'legalRep'" class="fields">
          <p class="form-hint">Le membre étant mineur, merci d'indiquer les coordonnées du représentant légal.</p>
          <div class="form-row">
            <div class="form-group">
              <label for="lrFirstName">Prénom du représentant</label>
              <InputText id="lrFirstName" name="legalRepFirstName" fluid />
              <small v-if="$form.legalRepFirstName?.invalid" class="field-error">{{ $form.legalRepFirstName.error?.message }}</small>
            </div>
            <div class="form-group">
              <label for="lrLastName">Nom du représentant</label>
              <InputText id="lrLastName" name="legalRepLastName" fluid />
              <small v-if="$form.legalRepLastName?.invalid" class="field-error">{{ $form.legalRepLastName.error?.message }}</small>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="lrEmail">Email du représentant</label>
              <InputText id="lrEmail" name="legalRepEmail" type="email" fluid />
              <small v-if="$form.legalRepEmail?.invalid" class="field-error">{{ $form.legalRepEmail.error?.message }}</small>
            </div>
            <div class="form-group">
              <label for="lrPhone">Téléphone du représentant</label>
              <PhoneInput name="legalRepPhone" input-id="lrPhone" placeholder="Téléphone du représentant" />
              <small v-if="$form.legalRepPhone?.invalid" class="field-error">{{ $form.legalRepPhone.error?.message }}</small>
            </div>
          </div>
        </section>

        <!-- Étape : santé -->
        <section v-show="currentStep === 'health'" class="fields">
          <h3 class="form-title">Questionnaire de santé</h3>
          <p class="form-hint">
            Remplissez le questionnaire de santé FSGT (à télécharger ci-dessous).
            Il est à conserver&nbsp;; vous ne le transmettez pas au club.
          </p>
          <FormField v-slot="$field" name="healthDeclaration" class="checkbox-row">
            <Checkbox :model-value="$field.value" :binary="true" input-id="health" @update:model-value="(v) => $field.onChange({ value: v })" />
            <label for="health">
              J'atteste avoir répondu <strong>NON</strong> à toutes les rubriques du questionnaire de santé.
            </label>
          </FormField>
          <Message v-if="certRequired" severity="warn" :closable="false" class="form-error">
            Une réponse « oui » impose de joindre un certificat médical de non
            contre-indication de moins de 6 mois à l'étape suivante.
          </Message>
        </section>

        <!-- Étape : documents -->
        <section v-show="currentStep === 'documents'" class="fields">
          <h3 class="form-title">Pièces à joindre</h3>
          <p class="form-hint">PDF, PNG ou JPG, 5 Mo max par fichier.</p>
          <ul class="doc-list">
            <li v-for="doc in docItems" :key="doc.key" class="doc-item">
              <div class="doc-head">
                <i class="pi" :class="files[doc.key] ? 'pi-check-circle done' : 'pi-circle todo'"></i>
                <span class="doc-name">{{ doc.label }}</span>
                <span v-if="doc.required" class="req">*</span>
                <span v-else class="optional">facultatif</span>
              </div>
              <input type="file" :accept="doc.accept" @change="onFile(doc.key, $event)" />
            </li>
          </ul>
        </section>

        <!-- Étape : récapitulatif -->
        <section v-show="currentStep === 'review'" class="fields">
          <h3 class="form-title">Récapitulatif</h3>
          <ul class="recap">
            <li><strong>{{ $form.firstName?.value }} {{ $form.lastName?.value }}</strong></li>
            <li>{{ $form.email?.value }} · {{ $form.phoneNumber?.value }}</li>
            <li>Né(e) le {{ formatDate($form.birthDate?.value) }}{{ isMinor ? ' (mineur)' : '' }}</li>
            <li v-if="isMinor">Représentant : {{ $form.legalRepFirstName?.value }} {{ $form.legalRepLastName?.value }}</li>
            <li>Attestation santé : {{ $form.healthDeclaration?.value ? 'aucune contre-indication' : 'certificat joint' }}</li>
            <li>Documents : {{ joinedDocLabels }}</li>
          </ul>
          <CommonRecaptcha ref="recaptcha" v-model="recaptchaToken" class="form-group" />
        </section>

        <!-- Documents officiels FSGT à télécharger -->
        <div v-if="stepDocs.length" class="docs">
          <span class="docs-title">Ressources</span>
          <a v-for="doc in stepDocs" :key="doc.url" :href="doc.url" target="_blank" rel="noopener" class="doc-link">
            <i class="pi pi-file-pdf"></i> {{ doc.label }}
          </a>
        </div>

        <!-- Navigation -->
        <div class="actions">
          <Button v-if="stepIndex > 0" type="button" label="Précédent" severity="secondary" outlined :disabled="sending" @click="prev" />
          <span class="spacer" />
          <Button v-if="currentStep !== 'review'" type="button" label="Suivant" icon="pi pi-arrow-right" icon-pos="right" @click="next" />
          <Button v-else type="submit" label="Envoyer ma demande" icon="pi pi-send" :loading="sending" />
        </div>
      </Form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Form, FormField } from '@primevue/forms'
import type { FormSubmitEvent } from '@primevue/forms'
import { zodResolver } from '@primevue/forms/resolvers/zod'
import { z } from 'zod'
import { isValidPhoneNumber } from 'libphonenumber-js'
import PhoneInput from '~/components/form/input/PhoneInput.vue'
import SelectInput from '~/components/form/input/SelectInput.vue'
import { LicenseRepository, type LicenseDocumentKey } from '~/repository/license-repository'
import { MemberGender, MemberGenderOptions } from '~/types/enum/MemberGender'

definePageMeta({ layout: 'public' })
useSeoMeta({
  title: 'Inscriptions - Clapiers Volley Ball',
  description: 'Demandez votre licence au Clapiers Volley Ball.',
})

const publicApi = usePublicApi()
const config = useRuntimeConfig()
const recaptchaEnabled = !!config.public.recaptchaSiteKey
const licenseRepo = new LicenseRepository()

const { season, fetchSeason } = useCurrentSeason()
onMounted(fetchSeason)

const genderOptions = MemberGenderOptions
const pad = (n: number) => String(n).padStart(2, '0')
const now = new Date()
const todayInput = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`
const { data: nationalities } = await useAsyncData(
  'nationalities',
  () => publicApi<string[]>('/public/nationalities'),
  { server: false, default: () => [] as string[] },
)

// Documents officiels FSGT, hébergés dans public/documents/fsgt.
const FSGT_DOCS = {
  questionnaireMineur: '/documents/fsgt/questionnaire-sante-mineur.pdf',
  attestationMineur: '/documents/fsgt/attestation-mineur.pdf',
  questionnaireMajeur: '/documents/fsgt/questionnaire-sante-majeur.pdf',
  attestationMajeur: '/documents/fsgt/attestation-majeur.pdf',
}

const isDateStr = (v: unknown): v is string => typeof v === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(v) && !isNaN(Date.parse(v))
const isMinorFromDate = (v: unknown): boolean => {
  if (!isDateStr(v)) return false
  const [y, m, d] = v.split('-').map(Number)
  const bd = new Date(y, m - 1, d)
  const ref = new Date()
  let age = ref.getFullYear() - bd.getFullYear()
  const mo = ref.getMonth() - bd.getMonth()
  if (mo < 0 || (mo === 0 && ref.getDate() < bd.getDate())) age--
  return age < 18
}
const formatDate = (v: unknown): string => {
  if (!isDateStr(v)) return ''
  const [y, m, d] = v.split('-')
  return `${d}/${m}/${y}`
}

const emailRule = z.string().min(1, { message: 'Email requis' }).email({ message: 'Email invalide' })
const phoneRule = z.string().min(1, { message: 'Numéro requis' }).refine((v) => isValidPhoneNumber(v, 'FR'), { message: 'Numéro invalide' })

const schema = z
  .object({
    firstName: z.string().min(1, { message: 'Le prénom est requis' }),
    lastName: z.string().min(1, { message: 'Le nom est requis' }),
    email: emailRule,
    phoneNumber: phoneRule,
    gender: z.nativeEnum(MemberGender, { message: 'Le genre est requis' }),
    birthDate: z.string().min(1, { message: 'La date de naissance est requise' }).refine(isDateStr, { message: 'Date invalide' }),
    nationality: z.string().min(1, { message: 'La nationalité est requise' }).max(100),
    addressStreet: z.string().min(1, { message: 'Adresse requise' }).max(255),
    addressZip: z.string().min(1, { message: 'Code postal requis' }).max(10),
    addressCity: z.string().min(1, { message: 'Ville requise' }).max(100),
    licenseNumber: z.string().max(50).nullable().optional(),
    healthDeclaration: z.boolean(),
    legalRepFirstName: z.string().optional(),
    legalRepLastName: z.string().optional(),
    legalRepEmail: z.string().optional(),
    legalRepPhone: z.string().optional(),
  })
  .superRefine((val, ctx) => {
    if (!isMinorFromDate(val.birthDate)) return
    const require = (field: 'legalRepFirstName' | 'legalRepLastName', message: string) => {
      if (!val[field]) ctx.addIssue({ code: z.ZodIssueCode.custom, path: [field], message })
    }
    require('legalRepFirstName', 'Prénom du représentant requis')
    require('legalRepLastName', 'Nom du représentant requis')
    if (!emailRule.safeParse(val.legalRepEmail).success) {
      ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['legalRepEmail'], message: 'Email du représentant invalide' })
    }
    if (!phoneRule.safeParse(val.legalRepPhone).success) {
      ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['legalRepPhone'], message: 'Téléphone du représentant invalide' })
    }
  })
const resolver = zodResolver(schema)

const initialValues = {
  firstName: '', lastName: '', email: '', phoneNumber: '',
  gender: null, birthDate: '', nationality: null,
  addressStreet: '', addressZip: '', addressCity: '', licenseNumber: '',
  healthDeclaration: true,
  legalRepFirstName: '', legalRepLastName: '', legalRepEmail: '', legalRepPhone: '',
}

const form = ref<any>(null)
const sending = ref(false)
const stepError = ref('')
const done = ref(false)
const recaptchaToken = ref('')
const recaptcha = ref<{ reset: () => void } | null>(null)

const fieldValue = (name: string) => form.value?.states?.[name]?.value
const isMinor = computed(() => isMinorFromDate(fieldValue('birthDate')))
const certRequired = computed(() => fieldValue('healthDeclaration') === false)
const healthDocUrl = computed(() => (isMinor.value ? FSGT_DOCS.questionnaireMineur : FSGT_DOCS.questionnaireMajeur))
const attestationUrl = computed(() => (isMinor.value ? FSGT_DOCS.attestationMineur : FSGT_DOCS.attestationMajeur))

const stepLabels: Record<string, string> = {
  identity: 'Identité', legalRep: 'Représentant', health: 'Santé', documents: 'Documents', review: 'Récapitulatif',
}
const stepFields: Record<string, string[]> = {
  identity: ['firstName', 'lastName', 'email', 'phoneNumber', 'gender', 'birthDate', 'nationality', 'addressStreet', 'addressZip', 'addressCity'],
  legalRep: ['legalRepFirstName', 'legalRepLastName', 'legalRepEmail', 'legalRepPhone'],
  health: [],
  documents: [],
  review: [],
}
const steps = computed(() => {
  const s = ['identity']
  if (isMinor.value) s.push('legalRep')
  s.push('health', 'documents', 'review')
  return s
})
const stepIndex = ref(0)
const currentStep = computed(() => steps.value[stepIndex.value] as string)

const IMG = 'image/png,image/jpeg'
const PDF_IMG = 'application/pdf,image/png,image/jpeg'
const docItems = computed<{ key: LicenseDocumentKey; label: string; accept: string; required: boolean }[]>(() => [
  { key: 'profile_picture', label: 'Photo de profil', accept: IMG, required: true },
  { key: 'id_card', label: "Pièce d'identité", accept: PDF_IMG, required: true },
  { key: 'medical_certificate', label: 'Certificat médical', accept: PDF_IMG, required: certRequired.value },
  { key: 'attestation', label: "Attestation sur l'honneur", accept: PDF_IMG, required: !certRequired.value },
])

const files = ref<Record<LicenseDocumentKey, File | null>>({
  profile_picture: null, id_card: null, medical_certificate: null, attestation: null,
})
const docLabels: Record<LicenseDocumentKey, string> = {
  profile_picture: 'photo', id_card: "pièce d'identité", medical_certificate: 'certificat', attestation: 'attestation',
}
const joinedDocLabels = computed(() => {
  const present = (Object.keys(files.value) as LicenseDocumentKey[]).filter((k) => files.value[k]).map((k) => docLabels[k])
  return present.length ? present.join(', ') : 'aucun'
})

const stepDocs = computed<{ label: string; url: string }[]>(() => {
  const age = isMinor.value ? 'mineur' : 'majeur'
  const questionnaire = { label: `Questionnaire de santé FSGT — ${age}`, url: healthDocUrl.value }
  const attestation = { label: `Attestation sur l'honneur FSGT — ${age}`, url: attestationUrl.value }
  switch (currentStep.value) {
    case 'legalRep': return [{ label: 'Attestation FSGT à signer par le représentant légal', url: FSGT_DOCS.attestationMineur }]
    case 'health': return [questionnaire, attestation]
    case 'documents': return [attestation]
    default: return []
  }
})

const onFile = (key: LicenseDocumentKey, event: Event) => {
  const input = event.target as HTMLInputElement
  files.value[key] = input.files?.[0] ?? null
}

const next = async () => {
  const fields = stepFields[currentStep.value] ?? []
  await Promise.all(fields.map((f) => form.value?.validate(f)))
  const invalid = fields.find((f) => form.value?.states?.[f]?.invalid)
  if (invalid) {
    stepError.value = form.value?.states?.[invalid]?.error?.message || 'Veuillez corriger les champs en rouge.'
    return
  }
  if (currentStep.value === 'documents') {
    const missing = docItems.value.find((d) => d.required && !files.value[d.key])
    if (missing) {
      stepError.value = `Le document « ${missing.label} » est requis.`
      return
    }
  }
  stepError.value = ''
  stepIndex.value = Math.min(stepIndex.value + 1, steps.value.length - 1)
}
const prev = () => {
  stepError.value = ''
  stepIndex.value = Math.max(stepIndex.value - 1, 0)
}

const onSubmit = async (e: FormSubmitEvent) => {
  if (!e.valid) {
    stepError.value = 'Veuillez vérifier les informations saisies.'
    return
  }
  const missing = docItems.value.find((d) => d.required && !files.value[d.key])
  if (missing) {
    stepError.value = `Le document « ${missing.label} » est requis.`
    return
  }
  if (recaptchaEnabled && !recaptchaToken.value) {
    stepError.value = 'Veuillez valider le captcha.'
    return
  }

  sending.value = true
  stepError.value = ''
  const v = e.values as Record<string, any>
  try {
    const license = await licenseRepo.submitRequest({
      firstName: v.firstName,
      lastName: v.lastName,
      phoneNumber: v.phoneNumber,
      email: v.email,
      addressStreet: v.addressStreet,
      addressZip: v.addressZip,
      addressCity: v.addressCity,
      gender: v.gender,
      birthDate: v.birthDate,
      nationality: v.nationality,
      licenseNumber: v.licenseNumber || null,
      recaptchaToken: recaptchaToken.value,
      healthDeclaration: v.healthDeclaration,
      legalRepFirstName: isMinor.value ? v.legalRepFirstName : null,
      legalRepLastName: isMinor.value ? v.legalRepLastName : null,
      legalRepEmail: isMinor.value ? v.legalRepEmail : null,
      legalRepPhone: isMinor.value ? v.legalRepPhone : null,
    })

    const token = license.accessToken as string
    for (const key of Object.keys(files.value) as LicenseDocumentKey[]) {
      const file = files.value[key]
      if (file) await licenseRepo.uploadDocument(token, key, file)
    }

    done.value = true
  } catch (err: any) {
    stepError.value = err?.data?.message || 'Une erreur est survenue. Veuillez réessayer.'
    recaptcha.value?.reset()
  } finally {
    sending.value = false
  }
}
</script>

<style scoped>
.inscription-page {
  min-height: 60vh;
  padding: 4rem 2rem;
  background: var(--club-gradient);
}

.container {
  max-width: 720px;
  margin: 0 auto;
}

.page-head {
  text-align: center;
  margin-bottom: 2rem;
  color: white;
}

.section-label {
  text-transform: uppercase;
  letter-spacing: 0.08em;
  font-size: 0.8rem;
  color: var(--club-accent);
}

.page-head h1 {
  font-size: 2.25rem;
  font-weight: 700;
  margin: 0.5rem 0;
}

.page-head p {
  color: rgba(255, 255, 255, 0.85);
  line-height: 1.6;
  margin: 0 auto;
  max-width: 560px;
}

.card {
  display: block;
  background: white;
  border-radius: 20px;
  padding: 2.5rem;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
}

.steps {
  display: flex;
  gap: 0.5rem;
  list-style: none;
  padding: 0;
  margin: 0 0 2rem;
}

.steps li {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.72rem;
  color: #9ca3af;
  text-align: center;
}

.steps .dot {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  background: #e5e7eb;
  color: #6b7280;
  font-weight: 600;
  font-size: 0.85rem;
}

.steps li.active .dot {
  background: var(--club-secondary);
  color: white;
}

.steps li.done .dot {
  background: #22c55e;
  color: white;
}

.steps li.active,
.steps li.done {
  color: var(--club-dark);
}

.form-title {
  margin: 0 0 0.5rem;
  font-size: 1.25rem;
  color: var(--club-dark);
}

.form-hint {
  color: #6b7280;
  font-size: 0.9rem;
  margin: 0 0 1.5rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  font-size: 0.85rem;
  font-weight: 500;
  color: #444;
  margin-bottom: 0.5rem;
}

.form-group .optional {
  color: #9ca3af;
  font-weight: 400;
}

.field-error {
  display: block;
  margin-top: 0.25rem;
  color: #ef4444;
  font-size: 0.8rem;
}

.req {
  color: #ef4444;
}

.checkbox-row {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  font-size: 0.9rem;
  color: #444;
  margin-bottom: 1rem;
}

.checkbox-row label {
  margin: 0;
  font-weight: 400;
}

.doc-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.doc-item {
  padding: 1rem 0;
  border-bottom: 1px solid #f0f0f0;
}

.doc-item:last-child {
  border-bottom: none;
}

.doc-head {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}

.doc-head .pi.done {
  color: #22c55e;
}

.doc-head .pi.todo {
  color: #d1d5db;
}

.doc-name {
  font-weight: 500;
  color: #444;
}

.doc-head .optional {
  margin-left: auto;
  font-size: 0.8rem;
  color: #9ca3af;
}

.recap {
  list-style: none;
  padding: 0;
  margin: 0 0 1rem;
  color: #444;
}

.recap li {
  padding: 0.4rem 0;
  border-bottom: 1px solid #f0f0f0;
}

.docs {
  margin-top: 1.5rem;
  padding: 1rem 1.25rem;
  background: #f8fafc;
  border: 1px solid #eef2f7;
  border-radius: 12px;
}

.docs-title {
  display: block;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #9ca3af;
  margin-bottom: 0.5rem;
}

.doc-link {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--club-primary);
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 500;
}

.doc-link + .doc-link {
  margin-top: 0.4rem;
}

.actions {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-top: 1.5rem;
}

.actions .spacer {
  flex: 1;
}

.form-error {
  margin-bottom: 1rem;
}

.success {
  display: flex;
  align-items: center;
  gap: 1.5rem;
}

.success i {
  font-size: 3rem;
  color: #22c55e;
}

.success h3 {
  margin: 0 0 0.5rem;
  color: var(--club-dark);
}

.success p {
  margin: 0 0 1rem;
  color: #666;
}

.back-link {
  color: var(--club-primary);
  text-decoration: none;
  font-weight: 500;
}

@media (max-width: 600px) {
  .inscription-page {
    padding: 3rem 1rem;
  }

  .card {
    padding: 1.5rem;
  }

  .form-row {
    grid-template-columns: 1fr;
  }

  .steps .step-label {
    display: none;
  }
}
</style>
