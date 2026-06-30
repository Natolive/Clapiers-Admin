<template>
  <div class="inscription-page">
    <div class="container">
      <header class="page-head">
        <span class="section-label">Adhésion</span>
        <h1>Demande de licence</h1>
        <p>
          Remplissez le formulaire ci-dessous. Votre demande sera vérifiée par un
          responsable du club&nbsp;; vous recevrez ensuite un lien par e-mail pour
          régler votre licence en ligne.
        </p>
      </header>

      <!-- Étape finale : confirmation -->
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

      <!-- Étape 2 : certificat médical -->
      <form v-else-if="token" class="card form" @submit.prevent="handleUpload">
        <h3 class="form-title">Certificat médical</h3>
        <p class="form-hint">
          Joignez votre certificat médical ou attestation (PDF, PNG ou JPG, 5 Mo max).
          Cette étape est facultative ici&nbsp;: vous pourrez aussi le transmettre plus tard.
        </p>

        <Message v-if="error" severity="error" :closable="false" class="form-error">{{ error }}</Message>

        <div class="form-group">
          <input type="file" accept="application/pdf,image/png,image/jpeg" @change="onFileChange" />
        </div>

        <div class="actions">
          <Button type="button" label="Plus tard" severity="secondary" outlined @click="finish" />
          <Button type="submit" label="Envoyer le certificat" icon="pi pi-upload" :loading="sending" :disabled="!file" />
        </div>
      </form>

      <!-- Étape 1 : informations -->
      <form v-else class="card form" @submit.prevent="handleSubmit">
        <Message v-if="error" severity="error" :closable="false" class="form-error">{{ error }}</Message>

        <div class="form-row">
          <div class="form-group">
            <label for="firstName">Prénom</label>
            <InputText id="firstName" v-model="form.firstName" autocomplete="given-name" />
          </div>
          <div class="form-group">
            <label for="lastName">Nom</label>
            <InputText id="lastName" v-model="form.lastName" autocomplete="family-name" />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="email">Email</label>
            <InputText id="email" v-model="form.email" type="email" autocomplete="email" />
          </div>
          <div class="form-group">
            <label for="phoneNumber">Téléphone</label>
            <InputText id="phoneNumber" v-model="form.phoneNumber" type="tel" autocomplete="tel" placeholder="+33…" />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="gender">Genre</label>
            <Select id="gender" v-model="form.gender" :options="genderOptions" option-label="label" option-value="value" placeholder="Sélectionnez" />
          </div>
          <div class="form-group">
            <label for="birthDate">Date de naissance</label>
            <InputText id="birthDate" v-model="form.birthDate" type="date" />
          </div>
        </div>

        <div class="form-group">
          <label for="nationality">Nationalité</label>
          <Select id="nationality" v-model="form.nationality" :options="nationalities" filter placeholder="Sélectionnez" />
        </div>

        <div class="form-group">
          <label for="addressStreet">Adresse</label>
          <InputText id="addressStreet" v-model="form.addressStreet" autocomplete="street-address" placeholder="N° et rue" />
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="addressZip">Code postal</label>
            <InputText id="addressZip" v-model="form.addressZip" autocomplete="postal-code" />
          </div>
          <div class="form-group">
            <label for="addressCity">Ville</label>
            <InputText id="addressCity" v-model="form.addressCity" autocomplete="address-level2" />
          </div>
        </div>

        <div class="form-group">
          <label for="licenseNumber">N° de licence <span class="optional">(si renouvellement)</span></label>
          <InputText id="licenseNumber" v-model="form.licenseNumber" />
        </div>

        <CommonRecaptcha ref="recaptcha" v-model="recaptchaToken" class="form-group" />

        <Button type="submit" label="Envoyer ma demande" icon="pi pi-send" class="submit-btn" :loading="sending" />
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { LicenseRepository } from '~/repository/license-repository'
import { MemberGenderOptions } from '~/types/enum/MemberGender'

definePageMeta({ layout: 'public' })
useSeoMeta({
  title: 'Inscriptions - Clapiers Volley Ball',
  description: 'Demandez votre licence au Clapiers Volley Ball.',
})

const publicApi = usePublicApi()
const config = useRuntimeConfig()
const recaptchaEnabled = !!config.public.recaptchaSiteKey
const licenseRepo = new LicenseRepository()

const genderOptions = MemberGenderOptions
const { data: nationalities } = await useAsyncData(
  'nationalities',
  () => publicApi<string[]>('/public/nationalities'),
  { server: false, default: () => [] as string[] },
)

const sending = ref(false)
const error = ref('')
const token = ref<string | null>(null)
const done = ref(false)
const file = ref<File | null>(null)
const recaptchaToken = ref('')
const recaptcha = ref<{ reset: () => void } | null>(null)

const form = ref({
  firstName: '',
  lastName: '',
  email: '',
  phoneNumber: '',
  gender: null as string | null,
  birthDate: '',
  nationality: null as string | null,
  addressStreet: '',
  addressZip: '',
  addressCity: '',
  licenseNumber: '',
})

const handleSubmit = async () => {
  const f = form.value
  if (!f.firstName || !f.lastName || !f.email || !f.phoneNumber || !f.gender || !f.birthDate || !f.nationality || !f.addressStreet || !f.addressZip || !f.addressCity) {
    error.value = 'Veuillez remplir tous les champs obligatoires.'
    return
  }
  if (recaptchaEnabled && !recaptchaToken.value) {
    error.value = 'Veuillez valider le captcha.'
    return
  }

  sending.value = true
  error.value = ''
  try {
    const license = await licenseRepo.submitRequest({
      firstName: f.firstName,
      lastName: f.lastName,
      phoneNumber: f.phoneNumber,
      email: f.email,
      addressStreet: f.addressStreet,
      addressZip: f.addressZip,
      addressCity: f.addressCity,
      gender: f.gender as string,
      birthDate: f.birthDate,
      nationality: f.nationality as string,
      licenseNumber: f.licenseNumber || null,
      recaptchaToken: recaptchaToken.value,
    })
    token.value = license.accessToken
  } catch (e: any) {
    error.value = e?.data?.message || 'Une erreur est survenue. Veuillez réessayer.'
    recaptcha.value?.reset()
  } finally {
    sending.value = false
  }
}

const onFileChange = (event: Event) => {
  const input = event.target as HTMLInputElement
  file.value = input.files?.[0] ?? null
}

const handleUpload = async () => {
  if (!token.value || !file.value) return
  sending.value = true
  error.value = ''
  try {
    await licenseRepo.uploadMedicalCertificate(token.value, file.value)
    finish()
  } catch (e: any) {
    error.value = e?.data?.message || "Échec de l'envoi du certificat. Vérifiez le format (PDF, PNG, JPG)."
  } finally {
    sending.value = false
  }
}

const finish = () => {
  done.value = true
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
  background: white;
  border-radius: 20px;
  padding: 2.5rem;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
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

.form-group :deep(.p-inputtext),
.form-group :deep(.p-select) {
  width: 100%;
}

.submit-btn {
  width: 100%;
  margin-top: 0.5rem;
  background: var(--club-secondary);
  border-color: var(--club-secondary);
}

.actions {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  margin-top: 0.5rem;
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
}
</style>
