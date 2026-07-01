<template>
  <main class="pay">
    <section class="card">
      <div v-if="loading" class="centered">
        <ProgressSpinner style="width: 44px; height: 44px" />
      </div>

      <div v-else-if="notFound" class="centered">
        <i class="pi pi-times-circle icon-error" />
        <h1>Lien invalide</h1>
        <p class="muted">Ce lien de paiement n'est pas valide ou a expiré.</p>
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
            class="w-full"
            :loading="paying"
            @click="pay"
          />
          <p class="secure"><i class="pi pi-lock" /> Paiement sécurisé via HelloAsso</p>
        </div>

        <Message v-else-if="view.status === 'soumise'" severity="info" :closable="false">
          Votre demande est en attente de validation. Vous recevrez un e-mail dès qu'elle sera validée.
        </Message>
        <Message v-else-if="view.status === 'payee'" severity="success" :closable="false">
          Votre licence est réglée. Merci&nbsp;! 🎉
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
useSeoMeta({ title: 'Paiement de licence' })

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
  background: #f4f5f7;
}

.card {
  width: 100%;
  max-width: 420px;
  background: #fff;
  border-radius: 16px;
  padding: 2rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
}

.centered {
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
}

.icon-error {
  font-size: 2.5rem;
  color: #e11d48;
}

.eyebrow {
  margin: 0;
  font-size: 0.8rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #6b7280;
}

h1 {
  margin: 0.25rem 0 1.25rem;
  font-size: 1.5rem;
  color: #111827;
}

.muted {
  color: #6b7280;
  margin: 0;
  font-size: 0.9rem;
}

.pay-box {
  text-align: center;
  margin-top: 0.5rem;
}

.amount {
  font-size: 2.25rem;
  font-weight: 700;
  color: #111827;
  margin: 0.25rem 0 1.25rem;
}

.w-full {
  width: 100%;
}

.secure {
  color: #9ca3af;
  font-size: 0.8rem;
  margin-top: 0.9rem;
}
</style>
