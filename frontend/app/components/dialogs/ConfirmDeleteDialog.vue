<template>
  <Dialog
    :visible="visible"
    :header="header"
    :modal="modal"
    :style="style"
    @update:visible="handleVisibilityChange"
  >
    <div class="flex align-items-center gap-3 mb-4">
      <i class="pi pi-exclamation-triangle text-orange-500" style="font-size: 2rem" />
      <span>{{ message }}</span>
    </div>

    <div class="flex justify-content-end gap-2">
      <Button
        label="Annuler"
        severity="secondary"
        text
        @click="handleVisibilityChange(false)"
      />
      <Button
        :label="confirmLabel"
        severity="danger"
        :loading="internalLoading"
        @click="handleConfirm"
      />
    </div>
  </Dialog>
</template>

<script setup lang="ts">
interface Props {
  visible?: boolean;
  header?: string;
  message?: string;
  modal?: boolean;
  style?: string | object;
  confirmLabel?: string;
  onConfirm?: () => void | Promise<void>;
}

const props = withDefaults(defineProps<Props>(), {
  visible: true,
  header: 'Confirmation de suppression',
  message: 'Êtes-vous sûr de vouloir supprimer cet élément ?',
  confirmLabel: 'Supprimer',
  modal: true,
  style: () => ({ width: '25rem' })
});

const emit = defineEmits<{
  'update:visible': [value: boolean];
}>();

const internalLoading = ref(false);

const handleVisibilityChange = (value: boolean) => {
  emit('update:visible', value);
};

const handleConfirm = async () => {
  if (props.onConfirm) {
    internalLoading.value = true;
    try {
      await props.onConfirm();
      emit('update:visible', false);
    } finally {
      internalLoading.value = false;
    }
  }
};
</script>
