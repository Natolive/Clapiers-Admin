<template>
  <PublicPage
    label="Contact"
    title="Nous contacter"
    subtitle="Une question sur les inscriptions, les tarifs, ou envie de venir faire un essai ? Écrivez-nous."
    wide
  >
    <div class="pp-cols">
      <!-- Coordonnées -->
      <section class="pp-block">
        <h2 class="pp-block__title"><i class="pi pi-map-marker"></i> Nos coordonnées</h2>
        <div class="methods">
          <a href="mailto:info@clapiersvb.fr" class="method">
            <div class="method-icon"><i class="pi pi-envelope"></i></div>
            <div class="method-content">
              <span class="method-label">Email</span>
              <span class="method-value">info@clapiersvb.fr</span>
            </div>
          </a>
          <a href="tel:+33609851673" class="method">
            <div class="method-icon"><i class="pi pi-phone"></i></div>
            <div class="method-content">
              <span class="method-label">Téléphone</span>
              <span class="method-value">+33 6 09 85 16 73</span>
            </div>
          </a>
          <div class="method">
            <div class="method-icon"><i class="pi pi-map-marker"></i></div>
            <div class="method-content">
              <span class="method-label">Adresse</span>
              <span class="method-value">Gymnase Joël Abati, 34830 Clapiers</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Formulaire -->
      <section class="pp-block">
        <div v-if="success" class="success-message">
          <i class="pi pi-check-circle"></i>
          <div>
            <h4>Message envoyé !</h4>
            <p>Nous vous répondrons dans les plus brefs délais.</p>
          </div>
        </div>

        <form v-else class="contact-form" @submit.prevent="handleSubmit">
          <h2 class="pp-block__title"><i class="pi pi-send"></i> Envoyer un message</h2>

          <Message v-if="error" severity="error" :closable="false" class="form-error">
            {{ error }}
          </Message>

          <div class="form-row">
            <div class="form-group">
              <label for="firstName">Prénom</label>
              <InputText id="firstName" v-model="form.firstName" placeholder="Votre prénom" autocomplete="given-name" />
            </div>
            <div class="form-group">
              <label for="lastName">Nom</label>
              <InputText id="lastName" v-model="form.lastName" placeholder="Votre nom" autocomplete="family-name" />
            </div>
          </div>

          <div class="form-group">
            <label for="email">Email</label>
            <InputText id="email" v-model="form.email" type="email" placeholder="votre@email.com" autocomplete="email" />
          </div>

          <div class="form-group">
            <label for="subject">Sujet</label>
            <Select id="subject" v-model="form.subject" :options="subjects" placeholder="Sélectionnez un sujet" />
          </div>

          <div class="form-group">
            <label for="message">Message</label>
            <Textarea id="message" v-model="form.message" rows="4" placeholder="Votre message..." autocomplete="off" />
          </div>

          <CommonRecaptcha ref="recaptcha" v-model="recaptchaToken" class="form-group" />

          <Button type="submit" label="Envoyer le message" icon="pi pi-send" class="submit-btn" :loading="sending" />
        </form>
      </section>
    </div>
  </PublicPage>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'public' })
useSeoMeta({
  title: 'Contact - Clapiers Volley Ball',
  description: 'Contactez le Clapiers Volley Ball pour rejoindre le club ou obtenir des informations sur les inscriptions. Répondez à vos questions via notre formulaire.',
  ogTitle: 'Contact - Clapiers Volley Ball',
  ogDescription: 'Contactez le Clapiers Volley Ball pour rejoindre le club ou obtenir des informations sur les inscriptions.',
  ogUrl: 'https://clapiersvb.fr/contact',
  twitterTitle: 'Contact - Clapiers Volley Ball',
  twitterDescription: 'Contactez le Clapiers Volley Ball pour rejoindre le club ou obtenir des informations sur les inscriptions.',
})
useHead({
  link: [{ rel: 'canonical', href: 'https://clapiersvb.fr/contact' }],
})

const api = usePublicApi()
const config = useRuntimeConfig()
const recaptchaEnabled = !!config.public.recaptchaSiteKey

const sending = ref(false)
const success = ref(false)
const error = ref('')
const recaptchaToken = ref('')
const recaptcha = ref<{ reset: () => void } | null>(null)

const form = ref({
  firstName: '',
  lastName: '',
  email: '',
  subject: null as string | null,
  message: ''
})

const subjects = [
  'Inscription',
  'Demande d\'essai',
  'Tarifs et cotisations',
  'Autre question'
]

const resetForm = () => {
  form.value = { firstName: '', lastName: '', email: '', subject: null, message: '' }
}

const handleSubmit = async () => {
  if (!form.value.firstName || !form.value.lastName || !form.value.email || !form.value.subject || !form.value.message) {
    error.value = 'Veuillez remplir tous les champs'
    return
  }

  if (recaptchaEnabled && !recaptchaToken.value) {
    error.value = 'Veuillez valider le captcha'
    return
  }

  sending.value = true
  error.value = ''
  success.value = false

  try {
    await api('/public/contact-message', {
      method: 'POST',
      body: {
        firstName: form.value.firstName,
        lastName: form.value.lastName,
        email: form.value.email,
        subject: form.value.subject,
        message: form.value.message,
        recaptchaToken: recaptchaToken.value
      }
    })
    success.value = true
    resetForm()
    recaptcha.value?.reset()
  } catch (e: any) {
    error.value = e?.data?.message || 'Une erreur est survenue. Veuillez réessayer.'
    recaptcha.value?.reset()
  } finally {
    sending.value = false
  }
}
</script>

<style scoped>
.methods {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.method {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: var(--club-light);
  border: 1px solid rgba(0, 0, 0, 0.05);
  border-radius: 12px;
  text-decoration: none;
  transition: all 0.2s ease;
}
.method:hover {
  transform: translateX(4px);
  border-color: rgba(30, 58, 95, 0.2);
}
.method-icon {
  width: 44px;
  height: 44px;
  flex-shrink: 0;
  background: var(--club-primary);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 1.1rem;
}
.method-content { display: flex; flex-direction: column; }
.method-label {
  font-size: 0.75rem;
  color: #9ca3af;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.method-value {
  color: var(--club-dark);
  font-weight: 500;
  font-size: 0.95rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}
.form-group { margin-bottom: 1rem; }
.form-group label {
  display: block;
  font-size: 0.85rem;
  font-weight: 500;
  color: #444;
  margin-bottom: 0.5rem;
}
.form-group :deep(.p-inputtext),
.form-group :deep(.p-select),
.form-group :deep(.p-textarea) {
  width: 100%;
}

.submit-btn {
  width: 100%;
  margin-top: 0.5rem;
  background: var(--club-secondary);
  border-color: var(--club-secondary);
}
.submit-btn:hover {
  background: #c5303c !important;
  border-color: #c5303c !important;
}

.success-message {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  padding: 1rem 0;
}
.success-message i { font-size: 3rem; color: #22c55e; }
.success-message h4 { margin: 0 0 0.5rem; font-size: 1.25rem; color: var(--club-dark); }
.success-message p { margin: 0; color: #666; }

.form-error { margin-bottom: 1rem; }

@media (max-width: 600px) {
  .form-row { grid-template-columns: 1fr; }
}
</style>
