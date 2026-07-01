<template>
  <div>
    <div class="flex flex-wrap gap-3 mb-3 align-items-end">
      <div>
        <label class="block mb-2 text-sm font-medium">Statut</label>
        <Select
          v-model="statusFilter"
          :options="statusOptions"
          option-label="label"
          option-value="value"
          class="w-14rem"
        />
      </div>
      <div class="flex-1" style="min-width: 12rem">
        <label class="block mb-2 text-sm font-medium">Recherche</label>
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText v-model="search" placeholder="Nom du membre…" class="w-full" />
        </IconField>
      </div>
    </div>

    <DataTable
      :value="items"
      :loading="loading"
      lazy
      striped-rows
      paginator
      :rows="rows"
      :total-records="total"
      :rows-per-page-options="[10, 25, 50]"
      :first="first"
      table-style="min-width: 48rem"
      class="p-datatable-sm"
      paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
      current-page-report-template="{first} à {last} sur {totalRecords}"
      @page="onPage"
    >
      <template #empty>
        <div class="text-center text-500 p-4">Aucune demande.</div>
      </template>

      <Column header="Membre">
        <template #body="{ data }">
          {{ data.member.firstName }} {{ data.member.lastName }}
        </template>
      </Column>
      <Column header="Email">
        <template #body="{ data }">{{ data.member.email }}</template>
      </Column>
      <Column header="Saison" field="season" />
      <Column header="Certificat">
        <template #body="{ data }">
          <i
            :class="data.medicalCertificateFileName ? 'pi pi-check-circle text-green-500' : 'pi pi-minus-circle text-400'"
            :title="data.medicalCertificateFileName ? 'Certificat déposé' : 'Aucun certificat'"
          />
        </template>
      </Column>
      <Column header="Statut">
        <template #body="{ data }">
          <Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" />
        </template>
      </Column>
      <Column header="Actions" style="width: 12rem">
        <template #body="{ data }">
          <div v-if="data.status === 'soumise'" class="flex gap-2">
            <Button label="Valider" icon="pi pi-check" size="small" severity="success" @click="openApprove(data)" />
            <Button icon="pi pi-times" size="small" severity="danger" outlined @click="openReject(data)" />
          </div>
          <span v-else class="text-500">—</span>
        </template>
      </Column>
    </DataTable>
  </div>
</template>

<script setup lang="ts">
import ApproveLicenseDialog from '~/components/dialogs/ApproveLicenseDialog.vue'
import RejectLicenseDialog from '~/components/dialogs/RejectLicenseDialog.vue'
import { LicenseAdminRepository } from '~/repository/license-admin-repository'
import type { License } from '~/types/entity/License'
import { LicenseStatus, LicenseStatusLabels } from '~/types/enum/LicenseStatus'

const repo = new LicenseAdminRepository()
const { show } = useDialogManager()

const items = ref<License[]>([])
const total = ref(0)
const loading = ref(false)
const first = ref(0)
const rows = ref(10)
const statusFilter = ref<string | null>(LicenseStatus.SOUMISE)
const search = ref('')
let searchTimeout: ReturnType<typeof setTimeout> | undefined

const statusOptions = [
  { label: 'Toutes', value: null },
  { label: 'Soumises', value: LicenseStatus.SOUMISE },
  { label: 'Validées', value: LicenseStatus.VALIDEE },
  { label: 'En paiement', value: LicenseStatus.EN_PAIEMENT },
  { label: 'Payées', value: LicenseStatus.PAYEE },
  { label: 'Refusées', value: LicenseStatus.REFUSEE },
]

const statusLabel = (status: LicenseStatus) => LicenseStatusLabels[status] ?? status
const statusSeverity = (status: string): string => ({
  soumise: 'info',
  validee: 'warn',
  en_paiement: 'warn',
  payee: 'success',
  refusee: 'danger',
  remboursee: 'secondary',
}[status] ?? 'secondary')

const fetchData = async () => {
  loading.value = true
  try {
    const result = await repo.getPaginated({
      page: Math.floor(first.value / rows.value) + 1,
      limit: rows.value,
      status: statusFilter.value ?? undefined,
      search: search.value.trim() || undefined,
    })
    items.value = result.data
    total.value = result.total
  } finally {
    loading.value = false
  }
}

const onPage = (event: { first: number; rows: number }) => {
  first.value = event.first
  rows.value = event.rows
  fetchData()
}

watch(statusFilter, () => { first.value = 0; fetchData() })
watch(search, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => { first.value = 0; fetchData() }, 300)
})

const openApprove = (license: License) => {
  show({ component: ApproveLicenseDialog, props: { license, onSaved: fetchData } })
}
const openReject = (license: License) => {
  show({ component: RejectLicenseDialog, props: { license, onSaved: fetchData } })
}

onMounted(fetchData)
defineExpose({ refresh: fetchData })
</script>
