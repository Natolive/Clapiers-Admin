<template>
  <section id="contact" class="vitrine-section surface-section">
    <div class="max-w-screen-xl mx-auto">
      <!-- Section Title -->
      <div class="text-center mb-6" ref="titleRef">
        <h2 class="text-5xl lg:text-6xl font-bold mb-3 gradient-text">
          Contactez-nous
        </h2>
        <p class="text-xl text-600 max-w-3xl mx-auto">
          Une question ? Envie de nous rejoindre ? N'hésitez pas à nous contacter !
        </p>
      </div>

      <!-- Contact Info Cards -->
      <div class="flex flex-wrap gap-4 justify-content-center mb-6" ref="infoRef">
        <!-- Address Card -->
        <Card class="hover-lift flex-1" style="min-width: 300px; max-width: 400px;">
          <template #content>
            <div class="flex flex-column align-items-center text-center gap-3">
              <div
                class="flex align-items-center justify-content-center border-circle"
                style="width: 4rem; height: 4rem; background: var(--vitrine-primary);"
              >
                <i class="pi pi-map-marker text-white text-3xl"></i>
              </div>
              <div>
                <h4 class="text-xl font-bold mb-2">Adresse</h4>
                <p class="text-600 m-0 line-height-3">{{ config.clubInfo.address }}</p>
              </div>
            </div>
          </template>
        </Card>

        <!-- Email Card -->
        <Card class="hover-lift flex-1" style="min-width: 300px; max-width: 400px;">
          <template #content>
            <div class="flex flex-column align-items-center text-center gap-3">
              <div
                class="flex align-items-center justify-content-center border-circle"
                style="width: 4rem; height: 4rem; background: var(--vitrine-primary);"
              >
                <i class="pi pi-envelope text-white text-3xl"></i>
              </div>
              <div>
                <h4 class="text-xl font-bold mb-2">Email</h4>
                <a
                  :href="`mailto:${config.clubInfo.email}`"
                  class="text-600 hover:text-primary no-underline"
                >
                  {{ config.clubInfo.email }}
                </a>
              </div>
            </div>
          </template>
        </Card>

        <!-- Phone Card -->
        <Card class="hover-lift flex-1" style="min-width: 300px; max-width: 400px;">
          <template #content>
            <div class="flex flex-column align-items-center text-center gap-3">
              <div
                class="flex align-items-center justify-content-center border-circle"
                style="width: 4rem; height: 4rem; background: var(--vitrine-primary);"
              >
                <i class="pi pi-phone text-white text-3xl"></i>
              </div>
              <div>
                <h4 class="text-xl font-bold mb-2">Téléphone</h4>
                <a
                  :href="`tel:${config.clubInfo.phone}`"
                  class="text-600 hover:text-primary no-underline"
                >
                  {{ config.clubInfo.phone }}
                </a>
              </div>
            </div>
          </template>
        </Card>
      </div>

      <!-- Contact Form - Centered -->
      <div class="flex justify-content-center mb-5">
        <div style="width: 100%; max-width: 800px;" ref="formRef">
          <Card>
            <template #title>
              <h3 class="text-2xl font-bold m-0 text-center">Envoyez-nous un message</h3>
            </template>
            <template #content>
              <Form
                v-slot="{ valid }"
                :initialValues="formData"
                :resolver="resolver"
                @submit="onSubmit"
                class="flex flex-column gap-4"
              >
                <div class="grid">
                  <!-- Name -->
                  <div class="col-12 md:col-6">
                    <div class="flex flex-column gap-2">
                      <label for="name" class="font-semibold">Nom complet</label>
                      <InputText
                        id="name"
                        v-model="formData.name"
                        placeholder="Votre nom"
                        class="w-full"
                      />
                      <Message v-if="errors.name" severity="error">{{ errors.name }}</Message>
                    </div>
                  </div>

                  <!-- Email -->
                  <div class="col-12 md:col-6">
                    <div class="flex flex-column gap-2">
                      <label for="email" class="font-semibold">Email</label>
                      <InputText
                        id="email"
                        v-model="formData.email"
                        type="email"
                        placeholder="votre.email@exemple.fr"
                        class="w-full"
                      />
                      <Message v-if="errors.email" severity="error">{{ errors.email }}</Message>
                    </div>
                  </div>

                  <!-- Phone -->
                  <div class="col-12">
                    <div class="flex flex-column gap-2">
                      <label for="phone" class="font-semibold">Téléphone (optionnel)</label>
                      <InputText
                        id="phone"
                        v-model="formData.phone"
                        type="tel"
                        placeholder="+33 6 XX XX XX XX"
                        class="w-full"
                      />
                    </div>
                  </div>

                  <!-- Message -->
                  <div class="col-12">
                    <div class="flex flex-column gap-2">
                      <label for="message" class="font-semibold">Message</label>
                      <Textarea
                        id="message"
                        v-model="formData.message"
                        rows="5"
                        placeholder="Votre message..."
                        class="w-full"
                      />
                      <Message v-if="errors.message" severity="error">{{ errors.message }}</Message>
                    </div>
                  </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-content-center">
                  <Button
                    type="submit"
                    label="Envoyer le message"
                    icon="pi pi-send"
                    severity="warning"
                    size="large"
                    class="font-bold px-6"
                    :loading="isSubmitting"
                  />
                </div>
              </Form>
            </template>
          </Card>
        </div>
      </div>

      <!-- Social Media & Map -->
      <div class="grid gap-4">
        <div class="col-12 md:col-6">
          <Card>
            <template #title>
              <h4 class="text-xl font-bold m-0 text-center">Suivez-nous</h4>
            </template>
            <template #content>
              <div class="flex gap-3 justify-content-center">
                <a
                  v-if="config.clubInfo.socialMedia.facebook"
                  :href="config.clubInfo.socialMedia.facebook"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="flex align-items-center justify-content-center border-circle hover-scale"
                  style="width: 3.5rem; height: 3.5rem; background: var(--vitrine-primary); text-decoration: none;"
                >
                  <i class="pi pi-facebook text-white text-2xl"></i>
                </a>
                <a
                  v-if="config.clubInfo.socialMedia.instagram"
                  :href="config.clubInfo.socialMedia.instagram"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="flex align-items-center justify-content-center border-circle hover-scale"
                  style="width: 3.5rem; height: 3.5rem; background: var(--vitrine-primary); text-decoration: none;"
                >
                  <i class="pi pi-instagram text-white text-2xl"></i>
                </a>
                <a
                  v-if="config.clubInfo.socialMedia.twitter"
                  :href="config.clubInfo.socialMedia.twitter"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="flex align-items-center justify-content-center border-circle hover-scale"
                  style="width: 3.5rem; height: 3.5rem; background: var(--vitrine-primary); text-decoration: none;"
                >
                  <i class="pi pi-twitter text-white text-2xl"></i>
                </a>
              </div>
            </template>
          </Card>
        </div>

        <div class="col-12 md:col-6">
          <div class="border-round-lg overflow-hidden shadow-3" style="height: 100%;">
            <iframe
              src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2888.1!2d3.8894!3d43.6525!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zNDPCsDM5JzA5LjAiTiAzwrA1Mycp!5e0!3m2!1sfr!2sfr!4v1234567890"
              width="100%"
              height="250"
              style="border:0;"
              allowfullscreen=""
              loading="lazy"
              referrerpolicy="no-referrer-when-downgrade"
            ></iframe>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { z } from 'zod'

const { config } = useVitrineConfig()
const { observe } = useScrollAnimation('fade-up')
const toast = usePVToastService()

const titleRef = ref<HTMLElement | null>(null)
const formRef = ref<HTMLElement | null>(null)
const infoRef = ref<HTMLElement | null>(null)

const formData = reactive({
  name: '',
  email: '',
  phone: '',
  message: ''
})

const errors = reactive({
  name: '',
  email: '',
  message: ''
})

const isSubmitting = ref(false)

// Validation schema
const contactSchema = z.object({
  name: z.string().min(2, 'Le nom doit contenir au moins 2 caractères'),
  email: z.string().email('Email invalide'),
  phone: z.string().optional(),
  message: z.string().min(10, 'Le message doit contenir au moins 10 caractères')
})

const resolver = (values: typeof formData) => {
  try {
    contactSchema.parse(values)
    errors.name = ''
    errors.email = ''
    errors.message = ''
    return { values, errors: {} }
  } catch (error) {
    if (error instanceof z.ZodError) {
      const fieldErrors: Record<string, string> = {}
      error.errors.forEach((err) => {
        if (err.path[0]) {
          fieldErrors[err.path[0] as string] = err.message
        }
      })
      errors.name = fieldErrors.name || ''
      errors.email = fieldErrors.email || ''
      errors.message = fieldErrors.message || ''
      return { values, errors: fieldErrors }
    }
    return { values, errors: {} }
  }
}

const onSubmit = async () => {
  // Validate
  const validation = resolver(formData)
  if (Object.keys(validation.errors).length > 0) {
    return
  }

  isSubmitting.value = true

  // Simulate form submission (since there's no backend)
  setTimeout(() => {
    isSubmitting.value = false

    toast.add({
      severity: 'success',
      summary: 'Message envoyé !',
      detail: 'Merci pour votre message. Nous vous répondrons dans les plus brefs délais.',
      life: 5000
    })

    // Reset form
    formData.name = ''
    formData.email = ''
    formData.phone = ''
    formData.message = ''
  }, 1500)
}

onMounted(() => {
  observe(titleRef.value)
  observe(formRef.value, 100)
  observe(infoRef.value, 200)
})
</script>

<style scoped>
.max-w-screen-xl {
  max-width: 1280px;
}

.max-w-3xl {
  max-width: 48rem;
}
</style>
