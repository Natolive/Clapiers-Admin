<template>
  <section id="contact" class="contact-section">
    <div class="container">
      <div class="contact-grid">
        <div class="contact-info">
          <span class="section-label">Contact</span>
          <h2 class="section-title white">
            Envie de<br />
            <span class="text-accent">nous rejoindre ?</span>
          </h2>
          <p class="contact-desc">
            N'hésitez pas à nous contacter pour toute question sur les
            inscriptions, les tarifs ou simplement pour venir faire un essai.
          </p>

          <div class="contact-methods">
            <a href="mailto:contact@vbc-clapiers.fr" class="contact-method">
              <div class="method-icon">
                <i class="pi pi-envelope"></i>
              </div>
              <div class="method-content">
                <span class="method-label">Email</span>
                <span class="method-value">contact@vbc-clapiers.fr</span>
              </div>
            </a>

            <a href="tel:+33600000000" class="contact-method">
              <div class="method-icon">
                <i class="pi pi-phone"></i>
              </div>
              <div class="method-content">
                <span class="method-label">Téléphone</span>
                <span class="method-value">06 00 00 00 00</span>
              </div>
            </a>

            <div class="contact-method">
              <div class="method-icon">
                <i class="pi pi-map-marker"></i>
              </div>
              <div class="method-content">
                <span class="method-label">Adresse</span>
                <span class="method-value">Gymnase Joël Abati, 34830 Clapiers</span>
              </div>
            </div>
          </div>

        </div>

        <div class="contact-form-wrapper">
          <form class="contact-form" @submit.prevent="handleSubmit">
            <h3 class="form-title">Envoyer un message</h3>

            <div class="form-row">
              <div class="form-group">
                <label for="firstName">Prénom</label>
                <InputText
                  id="firstName"
                  v-model="form.firstName"
                  placeholder="Votre prénom"
                />
              </div>
              <div class="form-group">
                <label for="lastName">Nom</label>
                <InputText
                  id="lastName"
                  v-model="form.lastName"
                  placeholder="Votre nom"
                />
              </div>
            </div>

            <div class="form-group">
              <label for="email">Email</label>
              <InputText
                id="email"
                v-model="form.email"
                type="email"
                placeholder="votre@email.com"
              />
            </div>

            <div class="form-group">
              <label for="subject">Sujet</label>
              <Select
                id="subject"
                v-model="form.subject"
                :options="subjects"
                placeholder="Sélectionnez un sujet"
              />
            </div>

            <div class="form-group">
              <label for="message">Message</label>
              <Textarea
                id="message"
                v-model="form.message"
                rows="4"
                placeholder="Votre message..."
              />
            </div>

            <Button
              type="submit"
              label="Envoyer le message"
              icon="pi pi-send"
              class="submit-btn"
              :loading="sending"
            />
          </form>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
const sending = ref(false)

const form = ref({
  firstName: '',
  lastName: '',
  email: '',
  subject: null,
  message: ''
})

const subjects = [
  'Inscription',
  'Demande d\'essai',
  'Tarifs et cotisations',
  'Autre question'
]

const handleSubmit = async () => {
  sending.value = true
  // Simulate sending
  await new Promise(resolve => setTimeout(resolve, 1500))
  sending.value = false
  // Reset form
  form.value = {
    firstName: '',
    lastName: '',
    email: '',
    subject: null,
    message: ''
  }
}
</script>

<style scoped>
.contact-section {
  padding: 6rem 2rem;
  background: var(--club-gradient);
}

.container {
  max-width: 1200px;
  margin: 0 auto;
}

.contact-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4rem;
  align-items: start;
}

.section-title.white {
  color: white;
}

.text-accent {
  color: var(--club-accent);
}

.contact-desc {
  color: rgba(255, 255, 255, 0.8);
  font-size: 1.05rem;
  line-height: 1.7;
  margin: 1.5rem 0 2.5rem;
}

.contact-methods {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.contact-method {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: rgba(255, 255, 255, 0.08);
  border-radius: 12px;
  text-decoration: none;
  transition: all 0.2s ease;
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.contact-method:hover {
  background: rgba(255, 255, 255, 0.12);
  transform: translateX(4px);
}

.method-icon {
  width: 44px;
  height: 44px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--club-accent);
  font-size: 1.1rem;
}

.method-content {
  display: flex;
  flex-direction: column;
}

.method-label {
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.6);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.method-value {
  color: white;
  font-weight: 500;
  font-size: 0.95rem;
}

.contact-form-wrapper {
  background: white;
  border-radius: 20px;
  padding: 2.5rem;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
}

.form-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--club-dark);
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

@media (max-width: 968px) {
  .contact-grid {
    grid-template-columns: 1fr;
    gap: 3rem;
  }

  .contact-info {
    text-align: center;
  }

  .contact-method {
    justify-content: center;
    text-align: left;
  }

  .social-links {
    justify-content: center;
  }
}

@media (max-width: 600px) {
  .contact-section {
    padding: 4rem 1.5rem;
  }

  .contact-form-wrapper {
    padding: 1.5rem;
  }

  .form-row {
    grid-template-columns: 1fr;
  }
}
</style>
