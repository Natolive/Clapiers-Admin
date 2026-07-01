<template>
  <div class="licence-page">
    <div class="container">
      <div class="card">
        <div v-if="loading" class="centered">
          <ProgressSpinner style="width: 48px; height: 48px" />
        </div>

        <div v-else-if="notFound" class="centered">
          <i class="pi pi-times-circle icon error"></i>
          <h2>Lien invalide</h2>
          <p>Ce lien de paiement n'est pas valide ou a expiré.</p>
        </div>

        <template v-else>
          <span class="section-label">Licence {{ view.season }}</span>
          <h1>Bonjour {{ view.firstName }},</h1>

          <Message v-if="returnStatus === 'success'" severity="info" :closable="false">
            Votre paiement est en cours de confirmation. Vous recevrez un e-mail dès qu'il sera validé.
          </Message>
          <Message v-else-if="returnStatus === 'error'" severity="error" :closable="false">
            Le paiement a échoué ou a été annulé. Vous pouvez réessayer ci-dessous.
          </Message>
          <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

          <!-- Payable -->
          <div v-if="payable" class="pay-box">
            <p class="amount-label">Montant de votre licence</p>
            <p class="amount">{{ formattedAmount }}</p>
            <Button
              label="Payer ma licence"
              icon="pi pi-credit-card"
              class="pay-btn"
              :loading="paying"
              @click="pay"
            />
            <p class="secure-note"><i class="pi pi-lock"></i> Paiement sécurisé via HelloAsso</p>
          </div>

          <!-- Autres états -->
          <Message v-else-if="view.status === 'soumise'" severity="info" :closable="false">
            Votre demande est en attente de validation par le club. Vous recevrez un e-mail
            dès qu'elle sera validée pour procéder au paiement.
          </Message>
          <Message v-else-if="view.status === 'payee'" severity="success" :closable="false">
            Votre licence est réglée. Merci&nbsp;! 🎉
          </Message>
          <Message v-else-if="view.status === 'refusee'" severity="warn" :closable="false">
            Votre demande de licence n'a pas été validée. Contactez le club pour plus d'informations.
          </Message>
          <Message v-else severity="info" :closable="false">
            Statut de la licence&nbsp;: {{ view.status }}
          </Message>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { LicenseRepository, type LicensePaymentView } from '~/repository/license-repository'

definePageMeta({ layout: 'public' })
useSeoMeta({ title: 'Paiement de licence - Clapiers Volley Ball' })

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
.licence-page {
  min-height: 60vh;
  padding: 4rem 2rem;
  background: var(--club-gradient);
  display: flex;
  align-items: center;
}

.container {
  max-width: 520px;
  margin: 0 auto;
  width: 100%;
}

.card {
  background: white;
  border-radius: 20px;
  padding: 2.5rem;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
}

.centered {
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}

.icon {
  font-size: 3rem;
}

.icon.error {
  color: #e63946;
}

.section-label {
  text-transform: uppercase;
  letter-spacing: 0.08em;
  font-size: 0.8rem;
  color: var(--club-accent);
}

h1 {
  font-size: 1.75rem;
  color: var(--club-dark);
  margin: 0.25rem 0 1.5rem;
}

.pay-box {
  text-align: center;
  margin-top: 1rem;
}

.amount-label {
  color: #6b7280;
  margin: 0;
  font-size: 0.9rem;
}

.amount {
  font-size: 2.5rem;
  font-weight: 700;
  color: var(--club-dark);
  margin: 0.25rem 0 1.5rem;
}

.pay-btn {
  width: 100%;
  background: var(--club-secondary);
  border-color: var(--club-secondary);
}

.secure-note {
  color: #9ca3af;
  font-size: 0.85rem;
  margin-top: 1rem;
}

@media (max-width: 600px) {
  .licence-page {
    padding: 3rem 1rem;
  }

  .card {
    padding: 1.5rem;
  }
}
</style>
