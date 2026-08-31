<template>
  <div class="media">
    <!-- Fil d'Ariane + actions -->
    <div class="media__bar">
      <Breadcrumb :home="home" :model="crumbs" class="media__crumbs">
        <template #item="{ item }">
          <a class="media__crumb" @click="item.command?.()">
            <i v-if="item.icon" :class="item.icon" />
            <span v-if="item.label">{{ item.label }}</span>
          </a>
        </template>
      </Breadcrumb>

      <div class="media__actions">
        <Button label="Dossier" icon="pi pi-folder-plus" size="small" outlined :disabled="busy" @click="openNewFolder" />
        <Button label="Document" icon="pi pi-upload" size="small" :disabled="busy" @click="triggerNewDocument" />
      </div>
    </div>

    <div v-if="loading" class="media__state">
      <ProgressSpinner style="width: 2.5rem; height: 2.5rem" />
    </div>

    <div v-else-if="!currentChildren.length" class="media__state media__state--empty">
      <i class="pi pi-inbox" />
      <span>Ce dossier est vide</span>
    </div>

    <div v-else class="media__grid">
      <div
        v-for="node in currentChildren"
        :key="node.id"
        class="media-card"
        :class="{ 'media-card--folder': isFolder(node), 'media-card--filled': !isFolder(node) && node.hasFile }"
        @dblclick="isFolder(node) && enterFolder(node)"
      >
        <button v-if="isFolder(node)" class="media-card__main" type="button" @click="enterFolder(node)">
          <i class="pi pi-folder media-card__icon" />
          <span class="media-card__name">{{ node.name }}</span>
        </button>

        <div v-else class="media-card__main media-card__main--doc">
          <i :class="documentIcon(node)" class="media-card__icon" />
          <span class="media-card__name">{{ node.name }}</span>
          <span class="media-card__meta">
            <template v-if="node.hasFile">{{ node.originalName }} · {{ formatSize(node.size) }}</template>
            <template v-else>Aucun fichier</template>
          </span>
        </div>

        <div class="media-card__badges">
          <Tag v-if="node.protected" value="Par défaut" severity="secondary" />
        </div>

        <div class="media-card__actions">
          <!-- Document -->
          <template v-if="!isFolder(node)">
            <Button
              v-if="node.hasFile"
              v-tooltip.top="'Voir'"
              icon="pi pi-eye" size="small" text rounded
              @click="view(node)"
            />
            <Button
              v-if="node.hasFile"
              v-tooltip.top="'Télécharger'"
              icon="pi pi-download" size="small" text rounded
              @click="download(node)"
            />
            <Button
              v-tooltip.top="node.hasFile ? 'Remplacer le fichier' : 'Importer un fichier'"
              icon="pi pi-upload" size="small" text rounded :disabled="busy"
              @click="triggerUpload(node)"
            />
            <Button
              v-if="node.hasFile"
              v-tooltip.top="'Retirer le fichier'"
              icon="pi pi-eraser" size="small" text rounded severity="warn" :disabled="busy"
              @click="clearFile(node)"
            />
          </template>

          <!-- Commun : renommer / supprimer (hors nœuds par défaut) -->
          <Button
            v-if="!node.protected"
            v-tooltip.top="'Renommer'"
            icon="pi pi-pencil" size="small" text rounded :disabled="busy"
            @click="openRename(node)"
          />
          <Button
            v-if="!node.protected"
            v-tooltip.top="'Supprimer'"
            icon="pi pi-trash" size="small" text rounded severity="danger" :disabled="busy"
            @click="confirmDelete(node)"
          />
        </div>
      </div>
    </div>

    <!-- Entrées fichier cachées -->
    <input ref="uploadInput" type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="onUploadFile" />
    <input ref="newDocInput" type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="onNewDocFile" />

    <!-- Dialog nouveau dossier -->
    <Dialog v-model:visible="folderDialog" modal header="Nouveau dossier" :style="{ width: '24rem' }">
      <div class="p-fluid">
        <label class="media-label" for="folder-name">Nom du dossier</label>
        <InputText id="folder-name" v-model="folderName" autofocus class="w-full" @keyup.enter="submitNewFolder" />
      </div>
      <template #footer>
        <Button label="Annuler" text @click="folderDialog = false" />
        <Button label="Créer" icon="pi pi-check" :disabled="!folderName.trim() || busy" @click="submitNewFolder" />
      </template>
    </Dialog>

    <!-- Dialog nommer le document -->
    <Dialog v-model:visible="docDialog" modal header="Nouveau document" :style="{ width: '24rem' }">
      <div class="p-fluid">
        <label class="media-label" for="doc-name">Nom du document</label>
        <InputText id="doc-name" v-model="docName" autofocus class="w-full" @keyup.enter="submitNewDocument" />
        <small class="media-hint">Fichier : {{ pendingFile?.name }}</small>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="cancelNewDocument" />
        <Button label="Ajouter" icon="pi pi-check" :disabled="!docName.trim() || busy" @click="submitNewDocument" />
      </template>
    </Dialog>

    <!-- Dialog renommer -->
    <Dialog v-model:visible="renameDialog" modal header="Renommer" :style="{ width: '24rem' }">
      <div class="p-fluid">
        <label class="media-label" for="rename">Nouveau nom</label>
        <InputText id="rename" v-model="renameValue" autofocus class="w-full" @keyup.enter="submitRename" />
      </div>
      <template #footer>
        <Button label="Annuler" text @click="renameDialog = false" />
        <Button label="Renommer" icon="pi pi-check" :disabled="!renameValue.trim() || busy" @click="submitRename" />
      </template>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import type { MemberDocument } from '~/types/entity/MemberDocument';
import { MemberDocumentType } from '~/types/enum/MemberDocumentType';
import { MemberMediaRepository } from '~/repository/member-media-repository';
import ConfirmDeleteDialog from '~/components/dialogs/ConfirmDeleteDialog.vue';

const props = defineProps<{ memberId: number }>();

const repo = new MemberMediaRepository();
const { show } = useDialogManager();
const toast = usePVToastService();

const roots = ref<MemberDocument[]>([]);
const pathIds = ref<string[]>([]);
const loading = ref(false);
const busy = ref(false);

const isFolder = (node: MemberDocument) => node.type === MemberDocumentType.FOLDER;

function findNode(nodes: MemberDocument[], id: string): MemberDocument | null {
  for (const node of nodes) {
    if (node.id === id) return node;
    const found = findNode(node.children, id);
    if (found) return found;
  }
  return null;
}

/** Nœuds du chemin courant, résolus contre l'arbre (les ids obsolètes sont ignorés). */
const path = computed<MemberDocument[]>(() => {
  const result: MemberDocument[] = [];
  let level = roots.value;
  for (const id of pathIds.value) {
    const node = level.find(n => n.id === id);
    if (!node) break;
    result.push(node);
    level = node.children;
  }
  return result;
});

const currentFolder = computed<MemberDocument | null>(() => path.value.at(-1) ?? null);
const currentParentId = computed<string | null>(() => currentFolder.value?.id ?? null);
const currentChildren = computed<MemberDocument[]>(() => currentFolder.value ? currentFolder.value.children : roots.value);

const home = computed(() => ({ icon: 'pi pi-home', command: () => { pathIds.value = []; } }));
const crumbs = computed(() =>
  path.value.map((node, index) => ({
    label: node.name,
    command: () => { pathIds.value = pathIds.value.slice(0, index + 1); },
  })),
);

async function load(): Promise<void> {
  loading.value = true;
  try {
    roots.value = await repo.getTree(props.memberId);
    // Élague le chemin si un dossier a disparu.
    pathIds.value = pathIds.value.filter(id => findNode(roots.value, id));
  } finally {
    loading.value = false;
  }
}

async function run(action: () => Promise<unknown>): Promise<void> {
  busy.value = true;
  try {
    await action();
    await load();
  } finally {
    busy.value = false;
  }
}

function enterFolder(node: MemberDocument): void {
  pathIds.value = [...pathIds.value, node.id];
}

function documentIcon(node: MemberDocument): string {
  if (!node.hasFile) return 'pi pi-file';
  if (node.mimeType?.startsWith('image/')) return 'pi pi-image';
  return 'pi pi-file-pdf';
}

function formatSize(size: number | null): string {
  if (!size) return '';
  if (size < 1024) return `${size} o`;
  if (size < 1024 * 1024) return `${Math.round(size / 1024)} Ko`;
  return `${(size / (1024 * 1024)).toFixed(1)} Mo`;
}

// ── Nouveau dossier ────────────────────────────────────────────────────────
const folderDialog = ref(false);
const folderName = ref('');
function openNewFolder(): void {
  folderName.value = '';
  folderDialog.value = true;
}
function submitNewFolder(): void {
  const name = folderName.value.trim();
  if (!name) return;
  folderDialog.value = false;
  run(() => repo.createFolder(props.memberId, name, currentParentId.value));
}

// ── Nouveau document ───────────────────────────────────────────────────────
const newDocInput = ref<HTMLInputElement | null>(null);
const docDialog = ref(false);
const docName = ref('');
const pendingFile = ref<File | null>(null);
function triggerNewDocument(): void {
  newDocInput.value?.click();
}
function onNewDocFile(event: Event): void {
  const file = (event.target as HTMLInputElement).files?.[0];
  if (event.target) (event.target as HTMLInputElement).value = '';
  if (!file) return;
  pendingFile.value = file;
  docName.value = file.name.replace(/\.[^.]+$/, '');
  docDialog.value = true;
}
function cancelNewDocument(): void {
  docDialog.value = false;
  pendingFile.value = null;
}
function submitNewDocument(): void {
  const name = docName.value.trim();
  if (!name || !pendingFile.value) return;
  const file = pendingFile.value;
  docDialog.value = false;
  pendingFile.value = null;
  run(() => repo.createDocument(props.memberId, name, file, currentParentId.value));
}

// ── Upload / remplacement sur un document existant ─────────────────────────
const uploadInput = ref<HTMLInputElement | null>(null);
const uploadTargetId = ref<string | null>(null);
function triggerUpload(node: MemberDocument): void {
  uploadTargetId.value = node.id;
  uploadInput.value?.click();
}
function onUploadFile(event: Event): void {
  const file = (event.target as HTMLInputElement).files?.[0];
  if (event.target) (event.target as HTMLInputElement).value = '';
  const target = uploadTargetId.value;
  uploadTargetId.value = null;
  if (!file || !target) return;
  run(() => repo.uploadFile(props.memberId, target, file));
}

function clearFile(node: MemberDocument): void {
  run(() => repo.deleteFile(props.memberId, node.id));
}

async function download(node: MemberDocument): Promise<void> {
  await repo.download(props.memberId, node.id, node.originalName ?? node.name);
}

// Nouvel onglet : la visionneuse PDF / image du navigateur fait le rendu.
async function view(node: MemberDocument): Promise<void> {
  try {
    await repo.view(props.memberId, node.id);
  } catch (e: any) {
    toast.add({ severity: 'error', summary: 'Aperçu impossible', detail: e?.message, life: 4000 });
  }
}

// ── Renommer ────────────────────────────────────────────────────────────────
const renameDialog = ref(false);
const renameValue = ref('');
const renameTarget = ref<MemberDocument | null>(null);
function openRename(node: MemberDocument): void {
  renameTarget.value = node;
  renameValue.value = node.name;
  renameDialog.value = true;
}
function submitRename(): void {
  const name = renameValue.value.trim();
  if (!name || !renameTarget.value) return;
  const id = renameTarget.value.id;
  renameDialog.value = false;
  run(() => repo.rename(props.memberId, id, name));
}

// ── Supprimer ─────────────────────────────────────────────────────────────
function confirmDelete(node: MemberDocument): void {
  show({
    component: ConfirmDeleteDialog,
    props: {
      message: isFolder(node)
        ? `Supprimer le dossier « ${node.name} » et tout son contenu ?`
        : `Supprimer le document « ${node.name} » ?`,
      onConfirm: () => run(async () => {
        await repo.deleteNode(props.memberId, node.id);
        toast.add({ severity: 'success', summary: 'Supprimé', detail: node.name, life: 3000 });
      }),
    },
  });
}

onMounted(load);
</script>

<style scoped>
.media {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  min-height: 18rem;
}

.media__bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}

.media__crumbs {
  background: transparent;
  border: none;
  padding: 0;
}

.media__crumb {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  cursor: pointer;
  color: var(--p-text-muted-color);
}
.media__crumb:hover { color: var(--p-primary-color); }

.media__actions { display: flex; gap: 0.5rem; }

.media__state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  padding: 3rem 1rem;
  color: var(--p-text-muted-color);
}
.media__state--empty i { font-size: 2rem; opacity: 0.5; }

.media__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 0.75rem;
}

.media-card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.85rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: var(--p-border-radius, 10px);
  background: var(--p-content-background);
  transition: border-color 0.15s, box-shadow 0.15s;
}
.media-card:hover { border-color: var(--p-primary-color); box-shadow: 0 2px 10px rgb(0 0 0 / 6%); }
.media-card--filled { border-left: 3px solid var(--p-primary-color); }

.media-card__main {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.35rem;
  background: none;
  border: none;
  text-align: left;
  cursor: pointer;
  color: inherit;
  padding: 0;
  font: inherit;
}
.media-card__main--doc { cursor: default; }

.media-card__icon { font-size: 1.5rem; color: var(--p-primary-color); }
.media-card--folder .media-card__icon { color: var(--p-amber-500, #f59e0b); }

.media-card__name { font-weight: 600; word-break: break-word; }
.media-card__meta { font-size: 0.75rem; color: var(--p-text-muted-color); word-break: break-word; }

.media-card__badges { position: absolute; top: 0.5rem; right: 0.5rem; }

.media-card__actions {
  display: flex;
  gap: 0.15rem;
  flex-wrap: wrap;
  margin-top: auto;
  padding-top: 0.35rem;
  border-top: 1px dashed var(--p-content-border-color);
}

.media-label { display: block; margin-bottom: 0.4rem; font-weight: 600; }
.media-hint { display: block; margin-top: 0.5rem; color: var(--p-text-muted-color); }
.hidden { display: none; }
</style>
