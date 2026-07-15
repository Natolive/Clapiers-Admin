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
        :disabled="!selectedTier"
        @click="confirm"
      />
    </div>
  </Dialog>
</template>

<script setup lang="ts">
import { LicenseAdminRepository, type LicenseTier } from '~/repository/license-admin-repository'
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

const formatAmount = (cents: number) => (cents / 100).toFixed(2).replace('.', ',') + ' €'

onMounted(async () => {
  try {
    tiers.value = await repo.getTiers()
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
    await repo.approve(props.license.id, selectedTier.value.id, selectedTier.value.amount)
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
