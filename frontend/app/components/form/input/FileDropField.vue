<template>
  <div class="file-drop-wrap">
    <div
      class="file-drop"
      :class="{
        'file-drop--active': isDragging,
        'file-drop--filled': !!fileName,
        'file-drop--busy': status === 'uploading',
        'file-drop--error': !!shownMessage && status !== 'done',
        'file-drop--done': status === 'done',
      }"
      role="button"
      tabindex="0"
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="onDrop"
      @click="pick"
      @keydown.enter.prevent="pick"
      @keydown.space.prevent="pick"
    >
      <input
        ref="inputRef"
        type="file"
        :accept="accept"
        class="file-drop__input"
        @change="onChange"
      />

      <template v-if="fileName">
        <i class="file-drop__icon" :class="stateIcon" />
        <div class="file-drop__meta">
          <span class="file-drop__name">{{ fileName }}</span>
          <span class="file-drop__size">{{ stateLabel }}</span>
        </div>
        <Button
          icon="pi pi-times"
          severity="secondary"
          text
          rounded
          size="small"
          :disabled="status === 'uploading'"
          v-tooltip.top="'Retirer'"
          @click.stop="emit('clear')"
        />
      </template>
      <template v-else>
        <i class="pi pi-cloud-upload file-drop__icon" />
        <span class="file-drop__hint">
          Glissez un fichier ou <span class="file-drop__browse">parcourir</span>
        </span>
      </template>
    </div>

    <small v-if="shownMessage" class="file-drop__message" :class="{ 'file-drop__message--error': status !== 'done' }">
      {{ shownMessage }}
    </small>
  </div>
</template>

<script setup lang="ts">
/**
 * Champ de dépôt d'une pièce. Il n'a pas de `v-model` : le fichier est envoyé
 * au serveur dès qu'il est choisi, et c'est l'appelant qui porte l'état de cet
 * envoi (`status`/`message`). Un `File` local ne suffirait pas de toute façon
 * pour une pièce reprise d'un brouillon — on n'en a plus que le nom.
 */
const props = withDefaults(defineProps<{
  accept?: string
  maxSize?: number
  /** Nom de la pièce attachée, fraîchement choisie ou déjà reçue par le serveur. */
  fileName?: string
  fileSize?: number | null
  status?: 'idle' | 'uploading' | 'done' | 'error'
  /** Message venant du serveur (refus, panne). Le contrôle local a le sien. */
  message?: string
}>(), { accept: '', maxSize: 5 * 1024 * 1024, fileName: '', fileSize: null, status: 'idle', message: '' })

const emit = defineEmits<{ select: [file: File]; clear: [] }>()

const inputRef = ref<HTMLInputElement | null>(null)
const isDragging = ref(false)
const localError = ref('')

const acceptList = computed(() =>
  props.accept.split(',').map((s) => s.trim()).filter(Boolean),
)

const shownMessage = computed(() => localError.value || props.message)

const stateIcon = computed(() => ({
  uploading: 'pi pi-spinner pi-spin file-drop__icon--filled',
  done: 'pi pi-check-circle file-drop__icon--done',
  error: 'pi pi-exclamation-circle file-drop__icon--error',
  idle: 'pi pi-file file-drop__icon--filled',
}[props.status]))

const stateLabel = computed(() => {
  if (props.status === 'uploading') return 'Envoi en cours…'
  if (props.status === 'error') return 'Non reçu'
  const size = props.fileSize ? ` · ${formatSize(props.fileSize)}` : ''

  return props.status === 'done' ? `Reçu par le club${size}` : formatSize(props.fileSize ?? 0)
})

const formatSize = (bytes: number) => {
  if (bytes < 1024) return `${bytes} o`
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} Ko`
  return `${(bytes / 1024 / 1024).toFixed(1)} Mo`
}

const isAccepted = (file: File) => {
  if (!acceptList.value.length) return true
  // Certains navigateurs (gestionnaires de fichiers Android, vieux Windows)
  // renvoient un type vide : laisser passer, le serveur valide le contenu.
  if (!file.type) return true
  return acceptList.value.some((a) =>
    a.startsWith('.')
      ? file.name.toLowerCase().endsWith(a.toLowerCase())
      : a.endsWith('/*')
        ? file.type.startsWith(a.slice(0, -1))
        : file.type === a,
  )
}

const pick = () => {
  if (props.status === 'uploading') return
  inputRef.value?.click()
}

const setFile = (file: File | null) => {
  localError.value = ''
  if (!file) return
  if (!isAccepted(file)) {
    localError.value = 'Format non accepté (PDF, PNG ou JPG).'
    return
  }
  if (file.size > props.maxSize) {
    localError.value = `Fichier trop volumineux (max ${formatSize(props.maxSize)}).`
    return
  }
  emit('select', file)
}

const onChange = (event: Event) => {
  const input = event.target as HTMLInputElement
  setFile(input.files?.[0] ?? null)
  input.value = '' // autorise la re-sélection du même fichier
}

const onDrop = (event: DragEvent) => {
  isDragging.value = false
  if (props.status === 'uploading') return
  setFile(event.dataTransfer?.files?.[0] ?? null)
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

.file-drop--busy {
  cursor: progress;
}

.file-drop--done {
  border-color: var(--p-green-400);
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

.file-drop__icon--done {
  color: var(--p-green-500);
}

.file-drop__icon--error {
  color: var(--p-red-500);
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

.file-drop__message {
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
}

.file-drop__message--error {
  color: var(--p-red-500);
}
</style>
