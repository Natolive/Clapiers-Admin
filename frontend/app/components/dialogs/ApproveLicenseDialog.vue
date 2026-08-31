<template>
  <Dialog
    :visible="visible"
    header="Valider la licence"
    :modal="true"
    :style="{ width: 'min(95vw, 480px)' }"
    @update:visible="onVisible"
  >
    <div class="mb-3">
      <p class="m-0"><strong>{{ license.member.firstName }} {{ license.member.lastName }}</strong></p>
      <p class="m-0 text-500">Saison {{ license.season }} · {{ license.member.email }}</p>
    </div>

    <Message v-if="error" severity="error" :closable="false" class="mb-3">{{ error }}</Message>

    <div v-if="existingMember" class="mb-4">
      <Message severity="warn" :closable="false" class="mb-2">
        Un licencié avec cet email existe déjà : {{ existingMember.firstName }} {{ existingMember.lastName }}.
      </Message>
      <label class="block mb-2 font-medium">Que faire de cette réinscription&nbsp;?</label>
      <Select
        v-model="duplicateChoice"
        :options="duplicateOptions"
        option-label="label"
        option-value="value"
        option-disabled="disabled"
        placeholder="Choisir…"
        class="w-full"
      />
      <small v-if="existingMember.hasLicenseThisSeason" class="text-500">
        Ce membre a déjà une licence pour la saison {{ license.season }} : le rattachement est indisponible.
      </small>
    </div>

    <div class="mb-4">
      <label class="block mb-2 font-medium">Tarif (formulaire HelloAsso)</label>
      <Select
        v-model="selectedTier"
        :options="tiers"
        option-label="label"
        placeholder="Choisir un tarif"
        :loading="loadingTiers"
        class="w-full"
      >
        <template #option="{ option }">{{ option.label }} — {{ formatAmount(option.amount) }}</template>
        <template #value="{ value }">
          <span v-if="value">{{ value.label }} — {{ formatAmount(value.amount) }}</span>
          <span v-else>Choisir un tarif</span>
        </template>
      </Select>
      <small v-if="!loadingTiers && tiers.length === 0" class="text-500">
        Aucun tarif trouvé. Vérifiez la configuration du formulaire d'adhésion HelloAsso.
      </small>
    </div>

    <div class="flex justify-content-end gap-2">
      <Button label="Annuler" severity="secondary" text @click="onVisible(false)" />
      <Button
        label="Valider et envoyer le lien"
        icon="pi pi-check"
        severity="success"
        :loading="loading"
        :disabled="!selectedTier || (!!existingMember && !duplicateChoice)"
        @click="confirm"
      />
    </div>
  </Dialog>
</template>

<script setup lang="ts">
import { LicenseAdminRepository, type LicenseTier, type LicenseReviewExistingMember } from '~/repository/license-admin-repository'
import type { License } from '~/types/entity/License'

const props = withDefaults(defineProps<{
  visible?: boolean
  license: License
  onSaved?: () => void
}>(), { visible: true })

const emit = defineEmits<{ 'update:visible': [value: boolean] }>()

const repo = new LicenseAdminRepository()
const toast = usePVToastService()

const tiers = ref<LicenseTier[]>([])
const selectedTier = ref<LicenseTier | null>(null)
const loading = ref(false)
const loadingTiers = ref(true)
const error = ref('')

const existingMember = ref<LicenseReviewExistingMember | null>(null)
const duplicateChoice = ref<'replace' | 'new' | null>(null)

const duplicateOptions = computed(() => existingMember.value ? [
  {
    label: `Rattacher à la fiche existante (${existingMember.value.firstName} ${existingMember.value.lastName})`,
    value: 'replace',
    disabled: existingMember.value.hasLicenseThisSeason,
  },
  { label: 'Créer un nouveau membre', value: 'new' },
] : [])

const formatAmount = (cents: number) => (cents / 100).toFixed(2).replace('.', ',') + ' €'

onMounted(async () => {
  try {
    const [tierList, review] = await Promise.all([repo.getTiers(), repo.getReview(props.license.id)])
    tiers.value = tierList
    existingMember.value = review.existingMember
  } catch {
    error.value = 'Impossible de charger les tarifs HelloAsso.'
  } finally {
    loadingTiers.value = false
  }
})

const onVisible = (value: boolean) => emit('update:visible', value)

const confirm = async () => {
  if (!selectedTier.value) return
  loading.value = true
  error.value = ''
  try {
    const replaceMemberId = duplicateChoice.value === 'replace' ? existingMember.value?.id ?? null : null
    await repo.approve(props.license.id, selectedTier.value.id, selectedTier.value.amount, replaceMemberId)
    toast.add({ severity: 'success', summary: 'Licence validée', detail: 'Le lien de paiement a été envoyé par e-mail.', life: 3000 })
    props.onSaved?.()
    emit('update:visible', false)
  } catch (e: any) {
    error.value = e?.data?.message || 'Erreur lors de la validation.'
  } finally {
    loading.value = false
  }
}
</script>
