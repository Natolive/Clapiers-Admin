<template>
  <Dialog
    :visible="visible"
    header="Refuser la demande"
    :modal="true"
    :style="{ width: 'min(95vw, 480px)' }"
    @update:visible="onVisible"
  >
    <div class="mb-3">
      <p class="m-0"><strong>{{ license.member.firstName }} {{ license.member.lastName }}</strong></p>
      <p class="m-0 text-500">Saison {{ license.season }}</p>
    </div>

    <Message v-if="error" severity="error" :closable="false" class="mb-3">{{ error }}</Message>

    <div class="mb-4">
      <label class="block mb-2 font-medium">Motif du refus</label>
      <Textarea v-model="reason" rows="4" class="w-full" placeholder="Communiqué au membre par e-mail…" />
    </div>

    <div class="flex justify-content-end gap-2">
      <Button label="Annuler" severity="secondary" text @click="onVisible(false)" />
      <Button
        label="Refuser"
        icon="pi pi-times"
        severity="danger"
        :loading="loading"
        :disabled="!reason.trim()"
        @click="confirm"
      />
    </div>
  </Dialog>
</template>

<script setup lang="ts">
import { LicenseAdminRepository } from '~/repository/license-admin-repository'
import type { License } from '~/types/entity/License'

const props = withDefaults(defineProps<{
  visible?: boolean
  license: License
  onSaved?: () => void
}>(), { visible: true })

const emit = defineEmits<{ 'update:visible': [value: boolean] }>()

const repo = new LicenseAdminRepository()
const toast = usePVToastService()

const reason = ref('')
const loading = ref(false)
const error = ref('')

const onVisible = (value: boolean) => emit('update:visible', value)

const confirm = async () => {
  if (!reason.value.trim()) return
  loading.value = true
  error.value = ''
  try {
    await repo.reject(props.license.id, reason.value.trim())
    toast.add({ severity: 'success', summary: 'Demande refusée', life: 3000 })
    props.onSaved?.()
    emit('update:visible', false)
  } catch (e: any) {
    error.value = e?.data?.message || 'Erreur lors du refus.'
  } finally {
    loading.value = false
  }
}
</script>
