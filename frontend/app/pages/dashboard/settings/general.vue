<template>
  <div>
    <Card>
      <template #title>Paramètres</template>
      <template #content>
        <Tabs value="general">
          <TabList>
            <Tab value="general"><i class="pi pi-sliders-h mr-2" /> Générale</Tab>
            <Tab value="helloasso"><i class="pi pi-credit-card mr-2" /> Paiement en ligne</Tab>
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
                  Deux réglages indépendants : l'annonce faite sur le site, et la
                  réception effective des demandes de licence.
                </p>

                <div v-if="inscriptionsLoading" class="text-color-secondary">Chargement…</div>
                <template v-else>
                  <div class="inscriptions-row">
                    <ToggleSwitch v-model="inscriptionsOpen" :disabled="inscriptionsSaving" @update:model-value="saveInscriptions({ open: $event })" />
                    <div>
                      <span>Affichage : inscriptions {{ inscriptionsOpen ? 'ouvertes' : 'clôturées' }}</span>
                      <small class="setting-note">Badge de la page d'accueil, purement indicatif.</small>
                    </div>
                  </div>
                  <div class="inscriptions-row">
                    <ToggleSwitch v-model="inscriptionsFormOpen" :disabled="inscriptionsSaving" @update:model-value="saveInscriptions({ formOpen: $event })" />
                    <div>
                      <span>Formulaire : demandes {{ inscriptionsFormOpen ? 'acceptées' : 'refusées' }}</span>
                      <small class="setting-note">Ferme réellement le formulaire d'inscription et l'API publique.</small>
                    </div>
                  </div>
                </template>
              </div>
            </TabPanel>

            <TabPanel value="helloasso">
              <div class="setting-block">
                <h3 class="setting-title">Paiement en ligne (HelloAsso)</h3>
                <p class="setting-help">
                  Identifiants de l'API HelloAsso et formulaire d'adhésion utilisé
                  pour les tarifs. Laissé vide, un champ garde sa valeur actuelle.
                  Sans ces réglages, le paiement en ligne des licences ne fonctionne pas.
                </p>

                <div v-if="helloAssoLoading" class="text-color-secondary">Chargement…</div>

                <template v-else>
                  <Message v-if="helloAssoError" severity="error" :closable="false" class="mb-3">{{ helloAssoError }}</Message>

                  <div class="helloasso-grid">
                    <div class="field">
                      <label for="ha-base-url">URL de l'API</label>
                      <InputText id="ha-base-url" v-model="helloAsso.baseUrl" fluid placeholder="https://api.helloasso.com" />
                      <small class="setting-note">Sandbox : <code>https://api.helloasso-sandbox.com</code></small>
                    </div>
                    <div class="field">
                      <label for="ha-org">Slug de l'association</label>
                      <InputText id="ha-org" v-model="helloAsso.organizationSlug" fluid placeholder="clapiers-volley" />
                    </div>
                    <div class="field">
                      <label for="ha-client-id">Client ID</label>
                      <InputText id="ha-client-id" v-model="helloAsso.clientId" fluid autocomplete="off" />
                    </div>
                    <div class="field">
                      <label for="ha-secret">Client secret</label>
                      <Password
                        input-id="ha-secret"
                        v-model="helloAssoSecret"
                        :feedback="false"
                        toggle-mask
                        fluid
                        autocomplete="new-password"
                        :placeholder="helloAsso.clientSecretDefined ? '•••••••• (inchangé)' : 'Non renseigné'"
                      />
                    </div>
                    <div class="field">
                      <label for="ha-form-type">Type de formulaire</label>
                      <InputText id="ha-form-type" v-model="helloAsso.membershipFormType" fluid placeholder="Event" />
                    </div>
                    <div class="field">
                      <label for="ha-form-slug">Slug du formulaire</label>
                      <InputText id="ha-form-slug" v-model="helloAsso.membershipFormSlug" fluid placeholder="adhesion-clapiers-volley-ball" />
                    </div>
                  </div>

                  <Button label="Enregistrer" icon="pi pi-check" :loading="helloAssoSaving" class="mt-3" @click="saveHelloAsso" />
                </template>
              </div>
            </TabPanel>
          </TabPanels>
        </Tabs>
      </template>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { SettingRepository, type HelloAssoConfig } from '~/repository/setting-repository'
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

const { open: sharedInscriptionsOpen, formOpen: sharedFormOpen } = useInscriptionsStatus()
const inscriptionsOpen = ref(true)
const inscriptionsFormOpen = ref(true)
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
    const status = await repo.getInscriptionsStatus()
    inscriptionsOpen.value = status.open
    inscriptionsFormOpen.value = status.formOpen
  } finally {
    inscriptionsLoading.value = false
  }
  try {
    helloAsso.value = await repo.getHelloAssoConfig()
  } finally {
    helloAssoLoading.value = false
  }
})

const saveInscriptions = async (change: { open?: boolean; formOpen?: boolean }) => {
  inscriptionsSaving.value = true
  try {
    const res = await repo.setInscriptionsStatus(change)
    inscriptionsOpen.value = res.open
    inscriptionsFormOpen.value = res.formOpen
    // Propage l'affichage public sans rechargement.
    sharedInscriptionsOpen.value = res.open
    sharedFormOpen.value = res.formOpen
    toast.add({
      severity: 'success',
      summary: 'Inscriptions',
      detail: change.open !== undefined
        ? (res.open ? 'Affichage : ouvertes' : 'Affichage : clôturées')
        : (res.formOpen ? 'Formulaire ouvert' : 'Formulaire fermé'),
      life: 3000,
    })
  } catch {
    // Revient à l'état précédent en cas d'échec.
    if (change.open !== undefined) inscriptionsOpen.value = !change.open
    if (change.formOpen !== undefined) inscriptionsFormOpen.value = !change.formOpen
    toast.add({ severity: 'error', summary: 'Inscriptions', detail: 'Enregistrement impossible.', life: 4000 })
  } finally {
    inscriptionsSaving.value = false
  }
}

const helloAsso = ref<HelloAssoConfig>({
  baseUrl: '', clientId: '', organizationSlug: '',
  membershipFormType: '', membershipFormSlug: '', clientSecretDefined: false,
})
const helloAssoSecret = ref('')
const helloAssoLoading = ref(true)
const helloAssoSaving = ref(false)
const helloAssoError = ref('')

const saveHelloAsso = async () => {
  helloAssoSaving.value = true
  helloAssoError.value = ''
  try {
    // Le secret n'est envoyé que s'il a été saisi ; vide = on garde l'existant.
    helloAsso.value = await repo.setHelloAssoConfig({
      baseUrl: helloAsso.value.baseUrl,
      clientId: helloAsso.value.clientId,
      organizationSlug: helloAsso.value.organizationSlug,
      membershipFormType: helloAsso.value.membershipFormType,
      membershipFormSlug: helloAsso.value.membershipFormSlug,
      clientSecret: helloAssoSecret.value || undefined,
    })
    helloAssoSecret.value = ''
    toast.add({ severity: 'success', summary: 'HelloAsso', detail: 'Configuration enregistrée', life: 3000 })
  } catch (e: any) {
    helloAssoError.value = e?.data?.message || 'Enregistrement impossible.'
  } finally {
    helloAssoSaving.value = false
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

.inscriptions-row + .inscriptions-row {
  margin-top: 1rem;
}

.setting-note {
  display: block;
  color: var(--p-text-muted-color);
}

.helloasso-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 1rem;
}

.helloasso-grid .field label {
  display: block;
  margin-bottom: 0.35rem;
  font-weight: 500;
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
