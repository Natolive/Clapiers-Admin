<template>
  <div class="file-drop-wrap">
    <div
      class="file-drop"
      :class="{
        'file-drop--active': isDragging,
        'file-drop--filled': !!modelValue,
        'file-drop--error': !!error,
      }"
      role="button"
      tabindex="0"
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="onDrop"
      @click="inputRef?.click()"
      @keydown.enter.prevent="inputRef?.click()"
      @keydown.space.prevent="inputRef?.click()"
    >
      <input
        ref="inputRef"
        type="file"
        :accept="accept"
        class="file-drop__input"
        @change="onChange"
      />

      <template v-if="modelValue">
        <i class="pi pi-file file-drop__icon file-drop__icon--filled" />
        <div class="file-drop__meta">
          <span class="file-drop__name">{{ modelValue.name }}</span>
          <span class="file-drop__size">{{ formatSize(modelValue.size) }}</span>
        </div>
        <Button
          icon="pi pi-times"
          severity="secondary"
          text
          rounded
          size="small"
          v-tooltip.top="'Retirer'"
          @click.stop="clear"
        />
      </template>
      <template v-else>
        <i class="pi pi-cloud-upload file-drop__icon" />
        <span class="file-drop__hint">
          Glissez un fichier ou <span class="file-drop__browse">parcourir</span>
        </span>
      </template>
    </div>

    <small v-if="error" class="file-drop__error">{{ error }}</small>
  </div>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{
  modelValue: File | null
  accept?: string
  maxSize?: number
}>(), { accept: '', maxSize: 5 * 1024 * 1024 })

const emit = defineEmits<{ 'update:modelValue': [value: File | null] }>()

const inputRef = ref<HTMLInputElement | null>(null)
const isDragging = ref(false)
const error = ref('')

const acceptList = computed(() =>
  props.accept.split(',').map((s) => s.trim()).filter(Boolean),
)

const formatSize = (bytes: number) => {
  if (bytes < 1024) return `${bytes} o`
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} Ko`
  return `${(bytes / 1024 / 1024).toFixed(1)} Mo`
}

const isAccepted = (file: File) => {
  if (!acceptList.value.length) return true
  return acceptList.value.some((a) =>
    a.startsWith('.')
      ? file.name.toLowerCase().endsWith(a.toLowerCase())
      : a.endsWith('/*')
        ? file.type.startsWith(a.slice(0, -1))
        : file.type === a,
  )
}

const setFile = (file: File | null) => {
  error.value = ''
  if (!file) {
    emit('update:modelValue', null)
    return
  }
  if (!isAccepted(file)) {
    error.value = 'Format non accepté (PDF, PNG ou JPG).'
    return
  }
  if (file.size > props.maxSize) {
    error.value = `Fichier trop volumineux (max ${formatSize(props.maxSize)}).`
    return
  }
  emit('update:modelValue', file)
}

const onChange = (event: Event) => {
  const input = event.target as HTMLInputElement
  setFile(input.files?.[0] ?? null)
  input.value = '' // autorise la re-sélection du même fichier
}

const onDrop = (event: DragEvent) => {
  isDragging.value = false
  setFile(event.dataTransfer?.files?.[0] ?? null)
}

const clear = () => {
  error.value = ''
  emit('update:modelValue', null)
  if (inputRef.value) inputRef.value.value = ''
}
</script>

<style scoped>
.file-drop-wrap {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.file-drop {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  border: 1.5px dashed var(--p-surface-border);
  border-radius: 10px;
  background: var(--p-surface-50);
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}

.file-drop:hover,
.file-drop:focus-visible {
  border-color: var(--p-primary-color);
  outline: none;
}

.file-drop--active {
  border-color: var(--p-primary-color);
  background: var(--p-primary-50);
}

.file-drop--filled {
  border-style: solid;
  background: var(--p-surface-0);
}

.file-drop--error {
  border-color: var(--p-red-400);
  background: var(--p-red-50);
}

.file-drop__input {
  display: none;
}

.file-drop__icon {
  font-size: 1.4rem;
  color: var(--p-text-muted-color);
  flex-shrink: 0;
}

.file-drop__icon--filled {
  color: var(--p-primary-color);
}

.file-drop__hint {
  font-size: 0.9rem;
  color: var(--p-text-muted-color);
}

.file-drop__browse {
  color: var(--p-primary-color);
  font-weight: 600;
}

.file-drop__meta {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}

.file-drop__name {
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.file-drop__size {
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
}

.file-drop__error {
  color: var(--p-red-500);
  font-size: 0.8rem;
}
</style>
