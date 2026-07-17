<template>
  <div>
    <Card>
      <template #title>Paramètres</template>
      <template #content>
        <Tabs value="general">
          <TabList>
            <Tab value="general"><i class="pi pi-sliders-h mr-2" /> Générale</Tab>
          </TabList>

          <TabPanels>
            <TabPanel value="general">
              <div class="setting-block">
                <h3 class="setting-title">Saison sportive courante</h3>
                <p class="setting-help">
                  Saison appliquée aux nouvelles demandes de licence. Format&nbsp;: <code>AAAA-AAAA</code>.
                </p>

                <div v-if="loading" class="text-color-secondary">Chargement…</div>

                <template v-else>
                  <Message v-if="error" severity="error" :closable="false" class="mb-3">{{ error }}</Message>

                  <div class="season-row">
                    <InputText
                      v-model="season"
                      placeholder="2025-2026"
                      class="season-input"
                      :invalid="!!error"
                    />
                    <Button label="Enregistrer" icon="pi pi-check" :loading="saving" @click="save" />
                  </div>

                  <p class="suggestion">
                    Suggestion (d'après la date)&nbsp;: <strong>{{ suggestion }}</strong>
                    <Button
                      v-if="season !== suggestion"
                      label="Utiliser"
                      link
                      size="small"
                      class="p-0 ml-1"
                      @click="season = suggestion"
                    />
                  </p>
                </template>
              </div>

              <div class="setting-block">
                <h3 class="setting-title">Inscriptions en ligne</h3>
                <p class="setting-help">
                  Affiche « inscriptions ouvertes / fermées » sur la page d'accueil.
                </p>

                <div v-if="inscriptionsLoading" class="text-color-secondary">Chargement…</div>
                <div v-else class="inscriptions-row">
                  <ToggleSwitch v-model="inscriptionsOpen" :disabled="inscriptionsSaving" @update:model-value="saveInscriptions" />
                  <span>{{ inscriptionsOpen ? 'Inscriptions ouvertes' : 'Inscriptions fermées' }}</span>
                </div>
              </div>
            </TabPanel>
          </TabPanels>
        </Tabs>
      </template>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { SettingRepository } from '~/repository/setting-repository'
import { AppUserRole } from '~/types/entity/AppUser'

definePageMeta({
  middleware: 'auth-middleware',
  layout: 'dashboard',
  requiredRoles: [AppUserRole.SUPER_ADMIN],
  redirectTo: '/dashboard/calendar',
})

useHead({ title: 'Paramètres' })

const repo = new SettingRepository()
const toast = usePVToastService()
const { season: sharedSeason } = useCurrentSeason()
const { seasons: seasonList } = useSeasonFilter()

const season = ref('')
const suggestion = ref('')
const loading = ref(true)
const saving = ref(false)
const error = ref('')

const { open: sharedInscriptionsOpen } = useInscriptionsStatus()
const inscriptionsOpen = ref(true)
const inscriptionsLoading = ref(true)
const inscriptionsSaving = ref(false)

onMounted(async () => {
  try {
    const s = await repo.getSeason()
    season.value = s.season
    suggestion.value = s.suggestion
  } finally {
    loading.value = false
  }
  try {
    inscriptionsOpen.value = (await repo.getInscriptionsStatus()).open
  } finally {
    inscriptionsLoading.value = false
  }
})

const saveInscriptions = async (value: boolean) => {
  inscriptionsSaving.value = true
  try {
    const res = await repo.setInscriptionsStatus(value)
    inscriptionsOpen.value = res.open
    sharedInscriptionsOpen.value = res.open // propage l'affichage public sans rechargement
    toast.add({ severity: 'success', summary: 'Inscriptions', detail: res.open ? 'Ouvertes' : 'Fermées', life: 3000 })
  } catch {
    inscriptionsOpen.value = !value // revient à l'état précédent en cas d'échec
    toast.add({ severity: 'error', summary: 'Inscriptions', detail: 'Enregistrement impossible.', life: 4000 })
  } finally {
    inscriptionsSaving.value = false
  }
}

const save = async () => {
  if (!/^\d{4}-\d{4}$/.test(season.value)) {
    error.value = 'Format attendu : AAAA-AAAA.'
    return
  }
  saving.value = true
  error.value = ''
  try {
    const res = await repo.setSeason(season.value)
    season.value = res.season
    sharedSeason.value = res.season // propage l'affichage (bandeau, pages) sans rechargement
    seasonList.value = [] // force le rechargement de la liste (nouvelle saison enregistrée)
    toast.add({ severity: 'success', summary: 'Saison enregistrée', detail: res.season, life: 3000 })
  } catch (e: any) {
    error.value = e?.data?.message || 'Enregistrement impossible.'
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.setting-block {
  max-width: 520px;
  padding-top: 0.5rem;
}

.setting-title {
  margin: 0 0 0.25rem;
  font-size: 1.05rem;
  color: var(--p-text-color);
}

.setting-help {
  margin: 0 0 1.25rem;
  font-size: 0.9rem;
  color: var(--p-text-muted-color);
}

.season-row,
.inscriptions-row {
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

.season-input {
  width: 12rem;
}

.suggestion {
  margin: 0.9rem 0 0;
  font-size: 0.85rem;
  color: var(--p-text-muted-color);
}
</style>
