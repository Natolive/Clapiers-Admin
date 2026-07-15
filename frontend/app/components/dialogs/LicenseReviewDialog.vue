<template>
  <Dialog
    :visible="visible"
    :header="`Dossier de ${license.member.firstName} ${license.member.lastName}`"
    :modal="true"
    :style="{ width: 'min(96vw, 720px)' }"
    @update:visible="onVisible"
  >
    <div class="review">
      <!-- Statut + saison -->
      <div class="review__head">
        <Tag :value="statusLabel(license.status)" :severity="statusSeverity(license.status)" />
        <span class="text-color-secondary">Saison {{ license.season }} · reçue le {{ formatDate(license.createdAt) }}</span>
      </div>

      <Message v-if="license.status === 'refusee' && license.rejectionReason" severity="error" :closable="false">
        Motif du refus : {{ license.rejectionReason }}
      </Message>

      <!-- Informations de la demande -->
      <section class="review__section">
        <h4 class="review__title">Informations</h4>
        <div class="review__grid">
          <div class="review__field"><span class="review__label">Email</span><span>{{ license.member.email || '—' }}</span></div>
          <div class="review__field"><span class="review__label">Téléphone</span><span>{{ license.member.phoneNumber || '—' }}</span></div>
          <div class="review__field"><span class="review__label">Date de naissance</span><span>{{ formatDate(license.member.birthDate) }}</span></div>
          <div class="review__field"><span class="review__label">Genre</span><span>{{ genderLabel(license.member.gender) }}</span></div>
          <div class="review__field"><span class="review__label">Nationalité</span><span>{{ license.member.nationality || '—' }}</span></div>
          <div class="review__field"><span class="review__label">N° de licence</span><span>{{ license.member.licenseNumber || '—' }}</span></div>
          <div class="review__field review__field--wide">
            <span class="review__label">Adresse</span>
            <span>{{ formatAddress(license.member.address) }}</span>
          </div>
          <div class="review__field">
            <span class="review__label">Déclaration santé</span>
            <span>{{ healthLabel(license.healthDeclaration) }}</span>
          </div>
          <div v-if="license.amount !== null" class="review__field">
            <span class="review__label">Montant</span><span>{{ formatAmount(license.amount) }}</span>
          </div>
        </div>

        <!-- Représentant légal (mineur) -->
        <template v-if="hasLegalRepresentative">
          <h4 class="review__title mt-3">Représentant légal</h4>
          <div class="review__grid">
            <div class="review__field">
              <span class="review__label">Nom</span>
              <span>{{ license.member.legalRepresentative!.firstName }} {{ license.member.legalRepresentative!.lastName }}</span>
            </div>
            <div class="review__field"><span class="review__label">Email</span><span>{{ license.member.legalRepresentative!.email }}</span></div>
            <div class="review__field"><span class="review__label">Téléphone</span><span>{{ license.member.legalRepresentative!.phone }}</span></div>
          </div>
        </template>
      </section>

      <!-- Pièces justificatives -->
      <section class="review__section">
        <h4 class="review__title">Pièces justificatives</h4>
        <div v-if="loading" class="flex flex-column gap-2">
          <Skeleton v-for="i in 4" :key="i" height="2.5rem" />
        </div>
        <Message v-else-if="error" severity="error" :closable="false">{{ error }}</Message>
        <ul v-else class="review__docs">
          <li v-for="doc in documents" :key="doc.key" class="review__doc">
            <div class="review__doc-info">
              <i :class="doc.uploaded ? 'pi pi-check-circle text-green-500' : 'pi pi-minus-circle text-color-secondary'" />
              <div class="flex flex-column">
                <span class="font-medium">{{ doc.label }}</span>
                <span v-if="doc.uploaded" class="text-sm text-color-secondary">{{ doc.originalName }}</span>
                <span v-else class="text-sm text-color-secondary">Non déposé</span>
              </div>
            </div>
            <Button
              v-if="doc.uploaded"
              label="Télécharger"
              icon="pi pi-download"
              size="small"
              severity="secondary"
              outlined
              :loading="downloading === doc.key"
              @click="download(doc)"
            />
          </li>
        </ul>
      </section>
    </div>

    <template #footer>
      <Button label="Fermer" severity="secondary" text @click="onVisible(false)" />
      <template v-if="license.status === 'soumise'">
        <Button label="Refuser" icon="pi pi-times" severity="danger" outlined @click="reject" />
        <Button label="Valider" icon="pi pi-check" severity="success" @click="approve" />
      </template>
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import ApproveLicenseDialog from '~/components/dialogs/ApproveLicenseDialog.vue'
import RejectLicenseDialog from '~/components/dialogs/RejectLicenseDialog.vue'
import { LicenseAdminRepository, type LicenseReviewDocument } from '~/repository/license-admin-repository'
import { MemberMediaRepository } from '~/repository/member-media-repository'
import type { License } from '~/types/entity/License'
import type { MemberGender } from '~/types/enum/MemberGender'
import { MemberGenderLabels } from '~/types/enum/MemberGender'
import { LicenseStatus, LicenseStatusLabels } from '~/types/enum/LicenseStatus'

const props = withDefaults(defineProps<{
  visible?: boolean
  license: License
  onSaved?: () => void
}>(), { visible: true })

const emit = defineEmits<{ 'update:visible': [value: boolean] }>()

const repo = new LicenseAdminRepository()
const mediaRepo = new MemberMediaRepository()
const toast = usePVToastService()
const { show } = useDialogManager()

const documents = ref<LicenseReviewDocument[]>([])
const memberId = ref<number>(props.license.member.id)
const loading = ref(true)
const error = ref('')
const downloading = ref<string | null>(null)

const statusLabel = (status: LicenseStatus) => LicenseStatusLabels[status] ?? status
const statusSeverity = (status: string): string => ({
  soumise: 'info', validee: 'warn', en_paiement: 'warn', payee: 'success', refusee: 'danger', remboursee: 'secondary',
}[status] ?? 'secondary')
const genderLabel = (gender: MemberGender) => MemberGenderLabels[gender] ?? '—'
const formatAmount = (cents: number) => (cents / 100).toFixed(2).replace('.', ',') + ' €'
const formatDate = (iso: string) => (iso ? new Date(iso).toLocaleDateString('fr-FR') : '—')
const formatAddress = (a: License['member']['address']) =>
  a && (a.street || a.city) ? `${a.street}, ${a.zip} ${a.city}`.trim() : '—'
const healthLabel = (declaration: boolean | null) =>
  declaration === true ? 'Aucun problème déclaré (pas de certificat requis)'
    : declaration === false ? 'Problème(s) déclaré(s) — certificat requis'
      : '—'

const hasLegalRepresentative = computed(() => {
  const rep = props.license.member.legalRepresentative
  return !!rep && !!(rep.firstName || rep.lastName || rep.email)
})

const onVisible = (value: boolean) => emit('update:visible', value)

onMounted(async () => {
  try {
    const review = await repo.getReview(props.license.id)
    documents.value = review.documents
    memberId.value = review.memberId
  } catch {
    error.value = 'Impossible de charger les pièces du dossier.'
  } finally {
    loading.value = false
  }
})

const download = async (doc: LicenseReviewDocument) => {
  if (!doc.nodeId) return
  downloading.value = doc.key
  try {
    await mediaRepo.download(memberId.value, doc.nodeId, doc.originalName ?? doc.label)
  } catch {
    toast.add({ severity: 'error', summary: 'Téléchargement impossible', detail: doc.label, life: 4000 })
  } finally {
    downloading.value = null
  }
}

const approve = () => {
  emit('update:visible', false)
  show({ component: ApproveLicenseDialog, props: { license: props.license, onSaved: props.onSaved } })
}

const reject = () => {
  emit('update:visible', false)
  show({ component: RejectLicenseDialog, props: { license: props.license, onSaved: props.onSaved } })
}
</script>

<style scoped>
.review {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.review__head {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.review__section {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.review__title {
  margin: 0;
  font-size: 0.95rem;
  color: var(--p-text-color);
}

.review__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.5rem 1.5rem;
}

.review__field {
  display: flex;
  flex-direction: column;
}

.review__field--wide {
  grid-column: 1 / -1;
}

.review__label {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--p-text-muted-color);
}

.review__docs {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.review__doc {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.6rem 0.85rem;
  border: 1px solid var(--p-surface-border);
  border-radius: 10px;
  background: var(--p-surface-card);
}

.review__doc-info {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.review__doc-info i {
  font-size: 1.1rem;
}

@media (max-width: 640px) {
  .review__grid {
    grid-template-columns: 1fr;
  }
}
</style>
