<template>
  <div>
    <div class="license-filters mb-3">
      <IconField class="license-filters__search">
        <InputIcon class="pi pi-search" />
        <InputText v-model="search" placeholder="Rechercher par nom du membre..." class="w-full" />
      </IconField>
      <Select
        v-model="statusFilter"
        :options="statusOptions"
        option-label="label"
        option-value="value"
        placeholder="Tous les statuts"
        show-clear
        class="license-filters__status"
      />
    </div>

    <!-- Desktop : tableau -->
    <DataTable
      v-if="!isMobile"
      :value="items"
      :loading="loading"
      lazy
      striped-rows
      paginator
      :rows="lazyParams.rows"
      :total-records="total"
      :rows-per-page-options="[10, 25, 50]"
      :first="lazyParams.first"
      table-style="min-width: 60rem"
      class="p-datatable-sm"
      paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
      current-page-report-template="{first} à {last} sur {totalRecords} demandes"
      @page="onPage"
    >
      <template #empty>
        <div class="datatable-empty"><i class="pi pi-inbox" /><span>Aucune demande</span></div>
      </template>

      <Column header="Membre" style="width: 22%">
        <template #body="{ data }">
          <div class="flex flex-column">
            <span class="font-medium">{{ data.member.firstName }} {{ data.member.lastName }}</span>
            <span class="text-sm text-color-secondary">{{ data.member.email }}</span>
          </div>
        </template>
      </Column>
      <Column field="member.phoneNumber" header="Téléphone" style="width: 16%">
        <template #body="{ data }">{{ data.member.phoneNumber }}</template>
      </Column>
      <Column field="season" header="Saison" style="width: 12%" />
      <Column header="Statut" style="width: 13%">
        <template #body="{ data }">
          <Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" />
        </template>
      </Column>
      <Column field="createdAt" header="Reçue le" style="width: 12%">
        <template #body="{ data }">{{ formatDate(data.createdAt) }}</template>
      </Column>
      <Column header="Actions" style="width: 22%">
        <template #body="{ data }">
          <div class="flex gap-2">
            <Button icon="pi pi-folder-open" size="small" severity="secondary" outlined v-tooltip.top="'Voir le dossier'" @click="openReview(data)" />
            <template v-if="data.status === 'soumise'">
              <Button label="Valider" icon="pi pi-check" size="small" severity="success" @click="openApprove(data)" />
              <Button icon="pi pi-times" size="small" severity="danger" outlined v-tooltip.top="'Refuser'" @click="openReject(data)" />
            </template>
            <Button
              v-else-if="canCopyLink(data)"
              label="Copier le lien"
              icon="pi pi-copy"
              size="small"
              severity="secondary"
              outlined
              @click="copyPaymentLink(data)"
            />
          </div>
        </template>
      </Column>
    </DataTable>

    <!-- Mobile : cartes -->
    <div v-else class="license-cards">
      <template v-if="loading">
        <div v-for="i in 4" :key="i" class="license-card">
          <Skeleton width="60%" height="1rem" class="mb-2" />
          <Skeleton width="40%" height="0.75rem" />
        </div>
      </template>

      <template v-else-if="items.length">
        <div v-for="license in items" :key="license.id" class="license-card">
          <div class="license-card__head">
            <span class="license-card__name">{{ license.member.firstName }} {{ license.member.lastName }}</span>
            <Tag :value="statusLabel(license.status)" :severity="statusSeverity(license.status)" class="text-xs" />
          </div>
          <span class="license-card__meta">{{ license.member.email }}</span>
          <span class="license-card__meta">Saison {{ license.season }} · reçue le {{ formatDate(license.createdAt) }}</span>
          <div class="license-card__actions">
            <Button label="Dossier" icon="pi pi-folder-open" size="small" severity="secondary" outlined class="flex-1" @click="openReview(license)" />
            <template v-if="license.status === 'soumise'">
              <Button label="Valider" icon="pi pi-check" size="small" severity="success" class="flex-1" @click="openApprove(license)" />
              <Button label="Refuser" icon="pi pi-times" size="small" severity="danger" outlined class="flex-1" @click="openReject(license)" />
            </template>
            <Button v-else-if="canCopyLink(license)" label="Copier le lien" icon="pi pi-copy" size="small" severity="secondary" outlined class="flex-1" @click="copyPaymentLink(license)" />
          </div>
        </div>
      </template>

      <div v-else class="datatable-empty"><i class="pi pi-inbox" /><span>Aucune demande</span></div>

      <Paginator
        v-if="total > lazyParams.rows"
        :rows="lazyParams.rows"
        :total-records="total"
        :first="lazyParams.first"
        template="PrevPageLink CurrentPageReport NextPageLink"
        current-page-report-template="{currentPage} / {totalPages}"
        @page="onPage"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import ApproveLicenseDialog from '~/components/dialogs/ApproveLicenseDialog.vue'
import RejectLicenseDialog from '~/components/dialogs/RejectLicenseDialog.vue'
import LicenseReviewDialog from '~/components/dialogs/LicenseReviewDialog.vue'
import { LicenseAdminRepository } from '~/repository/license-admin-repository'
import type { License } from '~/types/entity/License'
import { LicenseStatus, LicenseStatusLabels } from '~/types/enum/LicenseStatus'

const repo = new LicenseAdminRepository()
const { show } = useDialogManager()
const toast = usePVToastService()
const isMobile = useIsMobile()

// Le lien de paiement reste utile tant que la licence n'est pas réglée.
const canCopyLink = (license: License): boolean =>
  !!license.accessToken && ['validee', 'en_paiement'].includes(license.status)

const copyPaymentLink = async (license: License) => {
  const url = `${window.location.origin}/licence/${license.accessToken}`
  try {
    await navigator.clipboard.writeText(url)
    toast.add({ severity: 'success', summary: 'Lien copié', detail: url, life: 3000 })
  } catch {
    toast.add({ severity: 'warn', summary: 'Copie impossible', detail: url, life: 6000 })
  }
}

const items = ref<License[]>([])
const total = ref(0)
const loading = ref(false)
const statusFilter = ref<string | null>(LicenseStatus.SOUMISE)
const search = ref('')
const { selected: season, load: loadSeasons } = useSeasonFilter()
let searchTimeout: ReturnType<typeof setTimeout> | undefined

const lazyParams = ref({ first: 0, rows: 10 })

const statusOptions = [
  { label: 'Soumises', value: LicenseStatus.SOUMISE },
  { label: 'Validées', value: LicenseStatus.VALIDEE },
  { label: 'En paiement', value: LicenseStatus.EN_PAIEMENT },
  { label: 'Payées', value: LicenseStatus.PAYEE },
  { label: 'Refusées', value: LicenseStatus.REFUSEE },
]

const statusLabel = (status: LicenseStatus) => LicenseStatusLabels[status] ?? status
const statusSeverity = (status: string): string => ({
  soumise: 'info', validee: 'warn', en_paiement: 'warn', payee: 'success', refusee: 'danger', remboursee: 'secondary',
}[status] ?? 'secondary')
const formatDate = (iso: string) => new Date(iso).toLocaleDateString('fr-FR')

const fetchData = async () => {
  loading.value = true
  try {
    const result = await repo.getPaginated({
      page: Math.floor(lazyParams.value.first / lazyParams.value.rows) + 1,
      limit: lazyParams.value.rows,
      status: statusFilter.value ?? undefined,
      search: search.value.trim() || undefined,
      season: season.value || undefined,
    })
    items.value = result.data
    total.value = result.total
  } finally {
    loading.value = false
  }
}

const onPage = (event: { first: number; rows: number }) => {
  lazyParams.value.first = event.first
  lazyParams.value.rows = event.rows
  fetchData()
}

watch([statusFilter, season], () => { lazyParams.value.first = 0; fetchData() })
watch(search, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => { lazyParams.value.first = 0; fetchData() }, 300)
})

const openReview = (license: License) => show({ component: LicenseReviewDialog, props: { license, onSaved: fetchData } })
const openApprove = (license: License) => show({ component: ApproveLicenseDialog, props: { license, onSaved: fetchData } })
const openReject = (license: License) => show({ component: RejectLicenseDialog, props: { license, onSaved: fetchData } })

onMounted(() => { loadSeasons(); fetchData() })
defineExpose({ refresh: fetchData })
</script>

<style scoped>
.license-filters {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.license-filters__search {
  flex: 1 1 16rem;
}

.license-filters__status {
  width: 14rem;
}

@media (max-width: 767px) {
  .license-filters__search,
  .license-filters__status {
    flex: 1 1 100%;
    width: auto;
  }
}

.license-cards {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.license-card {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.85rem;
  border: 1px solid var(--p-surface-border);
  border-radius: 10px;
  background: var(--p-surface-card);
}

.license-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.license-card__name {
  font-weight: 600;
  color: var(--p-text-color);
}

.license-card__meta {
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
}

.license-card__actions {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.datatable-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  padding: 2.5rem 1rem;
  color: var(--p-text-muted-color);
}

.datatable-empty i {
  font-size: 2rem;
  opacity: 0.5;
}
</style>
