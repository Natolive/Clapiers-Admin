<template>
  <Dialog
    :visible="visible"
    header="Exporter les licenciés"
    :modal="true"
    :style="{ width: 'min(95vw, 640px)' }"
    @update:visible="onVisible"
  >
    <Message severity="info" :closable="false" class="mb-3">
      {{ filtersSummary }}
    </Message>

    <div class="mb-3">
      <label for="export-fsgt" class="block mb-2 font-medium">Inscription FSGT</label>
      <Select
        id="export-fsgt"
        v-model="fsgtChoice"
        :options="fsgtChoices"
        option-label="label"
        option-value="value"
        class="w-full"
        :disabled="restrictedToSelection"
      />
    </div>

    <div class="flex align-items-center justify-content-between mb-2">
      <span class="font-medium">Colonnes à exporter</span>
      <div class="flex gap-2">
        <Button label="Tout" size="small" severity="secondary" text @click="selectAll" />
        <Button label="Aucune" size="small" severity="secondary" text @click="selected = []" />
        <Button label="Par défaut" size="small" severity="secondary" text @click="selected = [...defaultColumns]" />
      </div>
    </div>

    <div class="export-groups">
      <div v-for="group in groups" :key="group.label" class="export-group">
        <div class="export-group__header">
          <Checkbox
            :model-value="isGroupChecked(group)"
            :indeterminate="isGroupPartial(group)"
            binary
            :input-id="`group-${group.label}`"
            @update:model-value="toggleGroup(group, $event)"
          />
          <label :for="`group-${group.label}`" class="font-medium">{{ group.label }}</label>
        </div>
        <div class="export-group__items">
          <div v-for="column in group.columns" :key="column" class="export-group__item">
            <Checkbox v-model="selected" :value="column" :input-id="column" />
            <label :for="column">{{ labels[column] }}</label>
          </div>
        </div>
      </div>
    </div>

    <div class="flex align-items-center justify-content-between mt-4 mb-2">
      <span class="font-medium">Pièces à joindre</span>
      <Button
        :label="selectedFiles.length ? 'Aucune pièce' : 'Toutes les pièces'"
        size="small"
        severity="secondary"
        text
        @click="toggleAllFiles"
      />
    </div>

    <div class="export-group">
      <div class="export-group__items">
        <div v-for="file in fileOptions" :key="file.value" class="export-group__item">
          <Checkbox v-model="selectedFiles" :value="file.value" :input-id="`file-${file.value}`" />
          <label :for="`file-${file.value}`">
            {{ file.label }}
            <small v-if="file.seasonScoped" class="text-500">(saison exportée)</small>
          </label>
        </div>
      </div>
    </div>

    <Message v-if="selectedFiles.length" severity="warn" :closable="false" class="mt-3">
      L'export sera une archive ZIP (tableau + un dossier de pièces par licencié).
      Selon le nombre de licenciés, sa préparation peut prendre un moment.
    </Message>

    <div class="flex align-items-center justify-content-between gap-2 mt-4">
      <small class="text-500">
        {{ selected.length }} colonne(s){{ selectedFiles.length ? `, ${selectedFiles.length} pièce(s)` : '' }}
      </small>
      <div class="flex gap-2">
        <Button label="Annuler" severity="secondary" text @click="onVisible(false)" />
        <Button
          :label="selectedFiles.length ? 'Exporter (ZIP)' : 'Exporter'"
          :icon="selectedFiles.length ? 'pi pi-file-export' : 'pi pi-file-excel'"
          :loading="loading"
          :disabled="selected.length === 0"
          @click="submit"
        />
      </div>
    </div>
  </Dialog>
</template>

<script setup lang="ts">
import { MemberRepository, type PaginationParams } from '~/repository/member-repository';
import {
  MemberExportColumn,
  MemberExportColumnGroups,
  MemberExportColumnLabels,
  MemberExportDefaultColumns,
} from '~/types/enum/MemberExportColumn';
import { MemberExportFile, MemberExportFileOptions } from '~/types/enum/MemberExportFile';

type ExportFilters = Omit<PaginationParams, 'page' | 'limit' | 'sortField' | 'sortOrder'>;
type ColumnGroup = (typeof MemberExportColumnGroups)[number];

const props = withDefaults(defineProps<{
  visible?: boolean;
  filters: ExportFilters;
  teamName?: string;
  /** Lignes cochées dans la liste ; vide = toute la population filtrée. */
  memberIds?: number[];
}>(), { visible: true, memberIds: () => [] });

const emit = defineEmits<{ 'update:visible': [value: boolean] }>();

const repository = new MemberRepository();
const toast = usePVToastService();

const groups = MemberExportColumnGroups;
const labels = MemberExportColumnLabels;
const defaultColumns = MemberExportDefaultColumns;
const selected = ref<MemberExportColumn[]>([...MemberExportDefaultColumns]);
// Rien par défaut : joindre les pièces transforme l'export en archive, ça se
// demande explicitement.
const fileOptions = MemberExportFileOptions;
const selectedFiles = ref<MemberExportFile[]>([]);
const loading = ref(false);

// Des lignes cochées l'emportent : filtrer en plus n'aurait pas de sens, on a
// déjà désigné qui exporter.
const restrictedToSelection = computed(() => props.memberIds.length > 0);

const fsgtChoices = [
  { label: 'Inscrits et non inscrits', value: 'all' },
  { label: 'Inscrits à la FSGT uniquement', value: 'yes' },
  { label: 'Non inscrits à la FSGT uniquement', value: 'no' },
];
// Initialisé sur le filtre déjà actif dans la liste : le dialog reflète l'écran.
const fsgtChoice = ref<'all' | 'yes' | 'no'>(
  props.filters.fsgtRegistered === undefined ? 'all' : (props.filters.fsgtRegistered ? 'yes' : 'no'),
);

// L'export reprend les filtres de la liste : le dire, sinon on croit toujours
// exporter tout le club alors qu'une recherche est active.
const filtersSummary = computed(() => {
  if (restrictedToSelection.value) {
    return `Export des ${props.memberIds.length} licencié(s) sélectionné(s) dans la liste.`;
  }

  const parts = [`Saison ${props.filters.season || 'courante'}`];
  if (props.teamName) parts.push(`équipe « ${props.teamName} »`);
  if (props.filters.licensePaid !== undefined) parts.push(props.filters.licensePaid ? 'licence payée' : 'licence non payée');
  if (props.filters.search) parts.push(`recherche « ${props.filters.search} »`);

  return `Export des licenciés affichés : ${parts.join(' · ')}.`;
});

const isGroupChecked = (group: ColumnGroup) => group.columns.every(c => selected.value.includes(c));
const isGroupPartial = (group: ColumnGroup) =>
  !isGroupChecked(group) && group.columns.some(c => selected.value.includes(c));

const toggleGroup = (group: ColumnGroup, checked: boolean) => {
  selected.value = checked
    ? [...new Set([...selected.value, ...group.columns])]
    : selected.value.filter(c => !group.columns.includes(c));
};

const selectAll = () => {
  selected.value = groups.flatMap(g => g.columns);
};

const toggleAllFiles = () => {
  selectedFiles.value = selectedFiles.value.length ? [] : fileOptions.map(f => f.value);
};

const onVisible = (value: boolean) => emit('update:visible', value);

const submit = async () => {
  loading.value = true;
  try {
    await repository.export(
      {
        ...props.filters,
        fsgtRegistered: fsgtChoice.value === 'all' ? undefined : fsgtChoice.value === 'yes',
      },
      selected.value,
      selectedFiles.value,
      props.memberIds,
    );
    onVisible(false);
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Export impossible',
      detail: e instanceof Error ? e.message : 'Le fichier n\'a pas pu être généré.',
      life: 5000,
    });
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.export-groups {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
  gap: 1rem;
}

.export-group {
  border: 1px solid var(--p-surface-border);
  border-radius: 8px;
  padding: 0.75rem;
}

.export-group__header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding-bottom: 0.5rem;
  margin-bottom: 0.5rem;
  border-bottom: 1px solid var(--p-surface-border);
}

.export-group__items {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.export-group__item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.export-group__item label {
  font-size: 0.875rem;
  cursor: pointer;
}
</style>
