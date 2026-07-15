<template>
  <div>
    <div class="log-toolbar mb-3">
      <IconField class="log-toolbar__search">
        <InputIcon class="pi pi-search" />
        <InputText v-model="search" placeholder="Rechercher dans le message..." class="w-full" />
      </IconField>
      <Select
        v-model="levelFilter"
        :options="levelOptions"
        option-label="label"
        option-value="value"
        placeholder="Tous les niveaux"
        show-clear
        class="log-toolbar__level"
      />
      <div class="log-toolbar__actions">
        <Button icon="pi pi-refresh" label="Actualiser" severity="secondary" outlined :loading="loading" @click="fetchData" />
        <Button icon="pi pi-trash" label="Vider" severity="danger" outlined :disabled="!total" @click="confirmClear = true" />
      </div>
    </div>

    <DataTable
      v-model:expanded-rows="expandedRows"
      :value="items"
      :loading="loading"
      lazy
      striped-rows
      paginator
      data-key="id"
      :rows="lazyParams.rows"
      :total-records="total"
      :rows-per-page-options="[25, 50, 100]"
      :first="lazyParams.first"
      table-style="min-width: 60rem"
      class="p-datatable-sm"
      paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
      current-page-report-template="{first} à {last} sur {totalRecords} logs"
      @page="onPage"
    >
      <template #empty>
        <div class="datatable-empty"><i class="pi pi-check-circle" /><span>Aucun log</span></div>
      </template>

      <Column expander style="width: 3rem" />
      <Column field="createdAt" header="Date" style="width: 12rem">
        <template #body="{ data }">{{ formatDate(data.createdAt) }}</template>
      </Column>
      <Column header="Niveau" style="width: 8rem">
        <template #body="{ data }">
          <Tag :value="data.level" :severity="levelSeverity(data.level)" />
        </template>
      </Column>
      <Column field="channel" header="Canal" style="width: 9rem">
        <template #body="{ data }"><span class="text-color-secondary">{{ data.channel }}</span></template>
      </Column>
      <Column header="Message">
        <template #body="{ data }"><span class="log-message">{{ data.message }}</span></template>
      </Column>

      <template #expansion="{ data }">
        <div class="log-context">
          <div class="log-context__message">{{ data.message }}</div>
          <pre v-if="data.context" class="log-context__json">{{ formatContext(data.context) }}</pre>
          <span v-else class="text-color-secondary">Aucun contexte</span>
        </div>
      </template>
    </DataTable>

    <Dialog v-model:visible="confirmClear" modal header="Vider les logs" :style="{ width: '28rem' }">
      <p class="m-0">Supprimer définitivement tous les logs enregistrés ?</p>
      <template #footer>
        <Button label="Annuler" severity="secondary" text @click="confirmClear = false" />
        <Button label="Vider" icon="pi pi-trash" severity="danger" :loading="clearing" @click="clear" />
      </template>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { LogRepository } from '~/repository/log-repository'
import type { LogEntry } from '~/types/entity/Log'

const repo = new LogRepository()
const toast = usePVToastService()

const items = ref<LogEntry[]>([])
const total = ref(0)
const loading = ref(false)
const clearing = ref(false)
const confirmClear = ref(false)
const expandedRows = ref<LogEntry[]>([])
const levelFilter = ref<string | null>(null)
const search = ref('')
let searchTimeout: ReturnType<typeof setTimeout> | undefined

const lazyParams = ref({ first: 0, rows: 50 })

const levelOptions = [
  { label: 'Emergency', value: 'emergency' },
  { label: 'Alert', value: 'alert' },
  { label: 'Critical', value: 'critical' },
  { label: 'Error', value: 'error' },
  { label: 'Warning', value: 'warning' },
  { label: 'Notice', value: 'notice' },
  { label: 'Info', value: 'info' },
  { label: 'Debug', value: 'debug' },
]

const levelSeverity = (level: string): string => ({
  emergency: 'danger', alert: 'danger', critical: 'danger', error: 'danger',
  warning: 'warn', notice: 'info', info: 'info', debug: 'secondary',
}[level] ?? 'secondary')

const formatDate = (iso: string) => new Date(iso).toLocaleString('fr-FR')
const formatContext = (context: Record<string, unknown>) => JSON.stringify(context, null, 2)

const fetchData = async () => {
  loading.value = true
  try {
    const result = await repo.getPaginated({
      page: Math.floor(lazyParams.value.first / lazyParams.value.rows) + 1,
      limit: lazyParams.value.rows,
      level: levelFilter.value ?? undefined,
      search: search.value.trim() || undefined,
    })
    items.value = result.data
    total.value = result.total
  } finally {
    loading.value = false
  }
}

const clear = async () => {
  clearing.value = true
  try {
    const { deleted } = await repo.clear()
    toast.add({ severity: 'success', summary: 'Logs vidés', detail: `${deleted} log(s) supprimé(s)`, life: 3000 })
    confirmClear.value = false
    lazyParams.value.first = 0
    await fetchData()
  } finally {
    clearing.value = false
  }
}

const onPage = (event: { first: number; rows: number }) => {
  lazyParams.value.first = event.first
  lazyParams.value.rows = event.rows
  fetchData()
}

watch(levelFilter, () => { lazyParams.value.first = 0; fetchData() })
watch(search, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => { lazyParams.value.first = 0; fetchData() }, 300)
})

onMounted(fetchData)
defineExpose({ refresh: fetchData })
</script>

<style scoped>
.log-toolbar {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.log-toolbar__search {
  flex: 1 1 16rem;
}

.log-toolbar__level {
  width: 14rem;
}

.log-toolbar__actions {
  display: flex;
  gap: 0.5rem;
  margin-left: auto;
}

.log-message {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 40rem;
}

.log-context {
  padding: 0.5rem 0.25rem;
}

.log-context__message {
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.log-context__json {
  margin: 0;
  padding: 0.75rem;
  border-radius: 8px;
  background: var(--p-surface-100);
  color: var(--p-text-color);
  font-size: 0.8rem;
  overflow-x: auto;
  white-space: pre-wrap;
  word-break: break-word;
}

@media (max-width: 767px) {
  .log-toolbar__search,
  .log-toolbar__level {
    flex: 1 1 100%;
    width: auto;
  }
  .log-toolbar__actions {
    margin-left: 0;
    flex: 1 1 100%;
  }
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
