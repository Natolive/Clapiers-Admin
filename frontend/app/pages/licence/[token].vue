<template>
  <main class="pay">
    <section class="card">
      <div class="brand">
        <span class="brand-dot"></span>
        <span>Clapiers <span class="brand-accent">Volley-Ball</span></span>
      </div>

      <div v-if="loading" class="centered">
        <ProgressSpinner style="width: 44px; height: 44px" stroke-width="4" />
      </div>

      <div v-else-if="notFound" class="centered">
        <div class="badge badge-error"><i class="pi pi-times" /></div>
        <h1>Lien invalide</h1>
        <p class="muted">Ce lien de paiement n'est pas valide ou a expiré.</p>
      </div>

      <!-- 🎉 Licence payée -->
      <div v-else-if="view.status === 'payee'" class="centered success">
        <p class="eyebrow">Licence {{ view.season }}</p>
        <h1>Merci {{ view.firstName }}&nbsp;!</h1>
        <p class="lead">Votre licence est <strong>réglée</strong>. Votre adhésion pour la saison
          {{ view.season }} est à jour.</p>
        <p v-if="view.amount !== null" class="paid-amount">{{ formattedAmount }} réglés</p>
        <p class="muted small">Un e-mail de confirmation vous a été envoyé. À très vite sur les terrains&nbsp;!</p>
      </div>

      <template v-else>
        <p class="eyebrow">Licence {{ view.season }}</p>
        <h1>Bonjour {{ view.firstName }}</h1>

        <Message v-if="returnStatus === 'success'" severity="info" :closable="false">
          Paiement en cours de confirmation. Vous recevrez un e-mail dès qu'il sera validé.
        </Message>
        <Message v-else-if="returnStatus === 'error'" severity="error" :closable="false">
          Le paiement a échoué ou a été annulé. Vous pouvez réessayer.
        </Message>
        <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

        <div v-if="payable" class="pay-box">
          <p class="muted">Montant de votre licence</p>
          <p class="amount">{{ formattedAmount }}</p>
          <Button
            label="Payer ma licence"
            icon="pi pi-credit-card"
            class="pay-btn w-full"
            :loading="paying"
            @click="pay"
          />
          <p class="secure"><i class="pi pi-lock" /> Paiement sécurisé via HelloAsso</p>
        </div>

        <Message v-else-if="view.status === 'soumise'" severity="info" :closable="false">
          Votre demande est en attente de validation. Vous recevrez un e-mail dès qu'elle sera validée.
        </Message>
        <Message v-else-if="view.status === 'refusee'" severity="warn" :closable="false">
          Votre demande n'a pas été validée. Contactez le club pour plus d'informations.
        </Message>
      </template>
    </section>
  </main>
</template>

<script setup lang="ts">
import { LicenseRepository, type LicensePaymentView } from '~/repository/license-repository'

definePageMeta({ layout: false })
useSeoMeta({ title: 'Paiement de licence — Clapiers Volley-Ball' })

const route = useRoute()
const token = route.params.token as string
const returnStatus = route.query.status as string | undefined
const licenseRepo = new LicenseRepository()

const loading = ref(true)
const notFound = ref(false)
const paying = ref(false)
const error = ref('')
const view = ref<LicensePaymentView>({ status: '', season: '', amount: null, firstName: '', lastName: '' })

const payable = computed(() => ['validee', 'en_paiement'].includes(view.value.status))
const formattedAmount = computed(() =>
  view.value.amount !== null ? (view.value.amount / 100).toFixed(2).replace('.', ',') + ' €' : '—',
)

onMounted(async () => {
  try {
    view.value = await licenseRepo.getForPayment(token)
  } catch {
    notFound.value = true
  } finally {
    loading.value = false
  }
})

const pay = async () => {
  paying.value = true
  error.value = ''
  try {
    const { redirectUrl } = await licenseRepo.createCheckout(token)
    if (redirectUrl) {
      window.location.href = redirectUrl
    } else {
      error.value = 'Impossible de démarrer le paiement. Réessayez plus tard.'
    }
  } catch (e: any) {
    error.value = e?.data?.message || 'Une erreur est survenue. Réessayez plus tard.'
    paying.value = false
  }
}
</script>

<style scoped>
.pay {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
  background: #1e3a5f;
  background-image: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
}

.card {
  width: 100%;
  max-width: 460px;
  background: #fff;
  border-radius: 22px;
  padding: 2.5rem 2rem;
  box-shadow: 0 24px 70px rgba(15, 26, 46, 0.35);
  text-align: center;
}

.brand {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-weight: 800;
  font-size: 0.95rem;
  color: #1a1a2e;
  letter-spacing: -0.01em;
  margin-bottom: 1.75rem;
}

.brand-accent { color: #f4a261; }

.brand-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #f4a261;
  display: inline-block;
}

.centered {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
}

.badge {
  width: 84px;
  height: 84px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2.4rem;
  color: #fff;
  margin-bottom: 0.5rem;
}

.badge-error {
  background: #e11d48;
  box-shadow: 0 12px 30px rgba(225, 29, 72, 0.35);
}

.eyebrow {
  margin: 0;
  font-size: 0.78rem;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #f4a261;
  font-weight: 700;
}

h1 {
  margin: 0.25rem 0 0.75rem;
  font-size: 1.75rem;
  font-weight: 800;
  color: #1a1a2e;
  letter-spacing: -0.02em;
}

.lead {
  margin: 0;
  font-size: 1rem;
  line-height: 1.6;
  color: #4b5563;
}

.paid-amount {
  margin: 1rem 0 0;
  display: inline-block;
  background: #f0fdf4;
  color: #15803d;
  font-weight: 700;
  font-size: 0.95rem;
  padding: 8px 18px;
  border-radius: 50px;
}

.muted { color: #6b7280; margin: 0; font-size: 0.9rem; }
.small { font-size: 0.82rem; margin-top: 0.75rem; }

.pay-box { margin-top: 0.5rem; }

.amount {
  font-size: 2.4rem;
  font-weight: 800;
  color: #1a1a2e;
  margin: 0.25rem 0 1.25rem;
  letter-spacing: -0.01em;
}

.w-full { width: 100%; }

.pay-btn {
  background: #e63946;
  border-color: #e63946;
  box-shadow: 0 10px 26px rgba(230, 57, 70, 0.32);
}

.pay-btn:hover {
  background: #c5303c !important;
  border-color: #c5303c !important;
}

.secure { color: #9ca3af; font-size: 0.8rem; margin-top: 0.9rem; }
</style>
