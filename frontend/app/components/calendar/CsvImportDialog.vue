<template>
    <Dialog
        :visible="visible"
        @update:visible="$emit('update:visible', $event)"
        header="Importer des matchs (CSV)"
        modal
        :style="{ width: '54rem', maxWidth: '96vw' }"
    >
        <div class="flex flex-column gap-4">

            <!-- Format documentation -->
            <Panel header="Format du fichier CSV attendu" toggleable :collapsed="formatCollapsed" @toggle="formatCollapsed = !formatCollapsed">
                <div class="flex flex-column gap-3">
                    <p class="text-sm text-color-secondary m-0">
                        Le fichier doit être encodé en <strong>UTF-8</strong>, avec une <strong>virgule</strong> comme séparateur.
                        La première ligne doit contenir exactement les en-têtes suivants (ordre libre) :
                    </p>

                    <div class="overflow-x-auto">
                        <table class="csv-doc-table">
                            <thead>
                                <tr>
                                    <th>Colonne</th>
                                    <th>Type</th>
                                    <th>Obligatoire</th>
                                    <th>Description / Exemple</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>team</code></td>
                                    <td>entier</td>
                                    <td class="text-center">✓</td>
                                    <td>Identifiant de l'équipe en base (ex&nbsp;: <code>3</code>)</td>
                                </tr>
                                <tr>
                                    <td><code>opponent</code></td>
                                    <td>texte (≤&nbsp;255)</td>
                                    <td class="text-center">✓</td>
                                    <td>Nom de l'équipe adverse (ex&nbsp;: <code>VLCA/LES AFFREUDITES</code>)</td>
                                </tr>
                                <tr>
                                    <td><code>date</code></td>
                                    <td>JJ/MM/AAAA</td>
                                    <td class="text-center">✓</td>
                                    <td>Date du match (ex&nbsp;: <code>23/03/2026</code>)</td>
                                </tr>
                                <tr>
                                    <td><code>venue</code></td>
                                    <td><code>HOME</code> / <code>AWAY</code></td>
                                    <td class="text-center">✓</td>
                                    <td>Réception à domicile ou à l'extérieur</td>
                                </tr>
                                <tr>
                                    <td><code>meetingTime</code></td>
                                    <td>HH:MM (≤&nbsp;10)</td>
                                    <td class="text-center text-color-secondary">—</td>
                                    <td>Heure de rendez-vous (ex&nbsp;: <code>19:00</code>)</td>
                                </tr>
                                <tr>
                                    <td><code>location</code></td>
                                    <td>texte (≤&nbsp;255)</td>
                                    <td class="text-center text-color-secondary">—</td>
                                    <td>Gymnase et ville (ex&nbsp;: <code>Gymnase d'Olympie (Montpellier)</code>)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="csv-example">
                        <span class="text-xs font-semibold text-color-secondary uppercase" style="letter-spacing:.05em">Exemple</span>
                        <pre class="csv-example__code">team,opponent,date,venue,meetingTime,location
3,VLCA/LES AFFREUDITES,23/03/2026,AWAY,19:00,Gymnase d'Olympie (Montpellier Antigone)
7,VCV/Titans du filet,26/03/2026,AWAY,21:00,Salle Polyvalente (Vailhauquès)</pre>
                    </div>

                    <Message severity="warn" :closable="false" size="small">
                        Toutes les lignes sont validées <strong>avant</strong> toute insertion.
                        En cas d'erreur, aucun match n'est importé (transaction avec rollback automatique).
                    </Message>
                </div>
            </Panel>

            <!-- File picker -->
            <div class="flex flex-column gap-2">
                <label class="font-medium text-sm">Fichier CSV <span class="text-red-500">*</span></label>

                <div
                    class="csv-drop-zone"
                    :class="{ 'csv-drop-zone--active': isDragging, 'csv-drop-zone--filled': !!selectedFile }"
                    @dragover.prevent="isDragging = true"
                    @dragleave="isDragging = false"
                    @drop.prevent="onDrop"
                    @click="fileInputRef?.click()"
                >
                    <input
                        ref="fileInputRef"
                        type="file"
                        accept=".csv,text/csv"
                        class="hidden"
                        @change="onFileChange"
                    />

                    <template v-if="selectedFile">
                        <i class="pi pi-file text-2xl text-primary" />
                        <div class="flex flex-column align-items-center gap-1">
                            <span class="font-medium">{{ selectedFile.name }}</span>
                            <span class="text-sm text-color-secondary">{{ formatSize(selectedFile.size) }}</span>
                        </div>
                        <Button
                            icon="pi pi-times"
                            severity="secondary"
                            text
                            rounded
                            size="small"
                            @click.stop="clearFile"
                        />
                    </template>
                    <template v-else>
                        <i class="pi pi-upload text-3xl text-color-secondary" />
                        <span class="text-color-secondary text-sm">
                            Glissez-déposez un fichier CSV ici ou <span class="text-primary font-medium">cliquez pour parcourir</span>
                        </span>
                    </template>
                </div>
            </div>

            <!-- Error message -->
            <Message v-if="errorMessage" severity="error" :closable="false">
                {{ errorMessage }}
            </Message>

            <!-- Actions -->
            <div class="flex justify-content-end gap-2 pt-2 border-top-1 surface-border">
                <Button
                    label="Annuler"
                    severity="secondary"
                    text
                    type="button"
                    :disabled="loading"
                    @click="$emit('update:visible', false)"
                />
                <Button
                    label="Importer"
                    icon="pi pi-upload"
                    type="button"
                    :loading="loading"
                    :disabled="!selectedFile"
                    @click="handleImport"
                />
            </div>
        </div>
    </Dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { GameRepository } from '~/repository/game-repository';

const emit = defineEmits<{
    'update:visible': [value: boolean];
    imported: [];
}>();

defineProps<{ visible: boolean }>();

const repository  = new GameRepository();
const toast       = usePVToastService();
const loading     = ref(false);
const errorMessage = ref<string | null>(null);
const selectedFile = ref<File | null>(null);
const isDragging   = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);
const formatCollapsed = ref(false);

const formatSize = (bytes: number): string => {
    if (bytes < 1024) return `${bytes} o`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} Ko`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`;
};

const setFile = (file: File | null) => {
    errorMessage.value = null;
    selectedFile.value = file;
};

const clearFile = () => {
    setFile(null);
    if (fileInputRef.value) fileInputRef.value.value = '';
};

const onFileChange = (e: Event) => {
    const input = e.target as HTMLInputElement;
    setFile(input.files?.[0] ?? null);
};

const onDrop = (e: DragEvent) => {
    isDragging.value = false;
    const file = e.dataTransfer?.files?.[0];
    if (file) setFile(file);
};

const handleImport = async () => {
    if (!selectedFile.value) return;
    loading.value     = true;
    errorMessage.value = null;

    try {
        const result = await repository.importCsv(selectedFile.value);
        toast.add({
            severity: 'success',
            summary:  'Import réussi',
            detail:   `${result.imported} match${result.imported > 1 ? 's' : ''} importé${result.imported > 1 ? 's' : ''} avec succès.`,
            life:     5000,
        });
        emit('imported');
        emit('update:visible', false);
        clearFile();
    } catch (e: any) {
        errorMessage.value = e?.data?.message ?? 'Une erreur est survenue lors de l\'import.';
    } finally {
        loading.value = false;
    }
};
</script>

<style scoped>
.csv-drop-zone {
    border: 2px dashed var(--p-surface-border);
    border-radius: 10px;
    padding: 2rem 1rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    text-align: center;
}

.csv-drop-zone:hover,
.csv-drop-zone--active {
    border-color: var(--p-primary-color);
    background: color-mix(in srgb, var(--p-primary-color) 5%, transparent);
}

.csv-drop-zone--filled {
    border-style: solid;
    border-color: var(--p-primary-color);
    background: color-mix(in srgb, var(--p-primary-color) 5%, transparent);
    flex-direction: row;
    padding: 1rem 1.25rem;
    justify-content: center;
    gap: 1rem;
}

.csv-doc-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8125rem;
}

.csv-doc-table th,
.csv-doc-table td {
    padding: 0.4rem 0.75rem;
    border: 1px solid var(--p-surface-border);
    vertical-align: top;
}

.csv-doc-table th {
    background: var(--p-surface-ground);
    font-weight: 600;
    white-space: nowrap;
}

.csv-doc-table code {
    font-family: monospace;
    font-size: 0.8rem;
    background: var(--p-surface-ground);
    padding: 1px 4px;
    border-radius: 3px;
}

.csv-example {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.csv-example__code {
    margin: 0;
    padding: 0.75rem 1rem;
    background: var(--p-surface-ground);
    border-radius: 6px;
    font-size: 0.75rem;
    font-family: monospace;
    overflow-x: auto;
    white-space: pre;
    line-height: 1.6;
    border: 1px solid var(--p-surface-border);
}
</style>
