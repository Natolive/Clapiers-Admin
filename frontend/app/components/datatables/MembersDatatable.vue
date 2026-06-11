<template>
  <div class="members-filters mb-3">
    <IconField class="members-filters__search">
      <InputIcon class="pi pi-search" />
      <InputText
        v-model="searchValue"
        placeholder="Rechercher par nom, prénom, email ou téléphone..."
        class="w-full"
      />
    </IconField>
    <Select
      v-model="selectedTeamId"
      :options="teamOptions"
      optionLabel="label"
      optionValue="value"
      placeholder="Toutes les équipes"
      showClear
      class="members-filters__team"
    />
    <div class="members-filters__toggles">
      <div class="flex align-items-center gap-2">
        <ToggleSwitch v-model="licensePaidFilter" />
        <span class="white-space-nowrap">Licence payée</span>
      </div>
      <div class="flex align-items-center gap-2">
        <ToggleSwitch v-model="hasLicenseFilter" />
        <span class="white-space-nowrap">Fichier licence</span>
      </div>
    </div>
  </div>

  <DataTable
    v-if="!isMobile"
    :value="members"
    :loading="loading"
    lazy
    stripedRows
    tableStyle="min-width: 64rem"
    class="p-datatable-sm"
    paginator
    :rows="lazyParams.rows"
    :totalRecords="totalRecords"
    :rowsPerPageOptions="[10, 25, 50]"
    paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
    currentPageReportTemplate="{first} à {last} sur {totalRecords} licenciés"
    :first="lazyParams.first"
    :sortField="lazyParams.sortField"
    :sortOrder="lazyParams.sortOrder"
    @page="onPage"
    @sort="onSort"
  >
    <template #empty>
      <div class="datatable-empty">
        <i class="pi pi-users" />
        <span>Aucun licencié trouvé</span>
      </div>
    </template>
    <Column header="Licencié" sortable field="firstName" style="width: 20%">
      <template #body="slotProps">
        <div class="flex align-items-center gap-3">
          <MemberAvatar
            :member="slotProps.data"
            size="normal"
            editable
            @upload="(file: File) => onUploadProfilePicture(slotProps.data, file)"
            @delete="onDeleteProfilePicture(slotProps.data)"
          />
          <span>{{ slotProps.data.firstName }} {{ slotProps.data.lastName }}</span>
        </div>
      </template>
    </Column>
    <Column field="phoneNumber" header="Téléphone" sortable style="width: 15%">
      <template #body="slotProps">
        <span>{{ slotProps.data.phoneNumber }}</span>
      </template>
    </Column>
    <Column field="email" header="Email" sortable style="width: 15%">
      <template #body="slotProps">
        <span>{{ slotProps.data.email }}</span>
      </template>
    </Column>
    <Column header="Équipes" style="width: 10%">
      <template #body="slotProps">
        <div class="flex gap-1 flex-wrap">
          <Tag
            v-for="team in slotProps.data.teams"
            :key="team.id"
            :value="team.name"
            severity="secondary"
            class="text-xs"
          />
        </div>
      </template>
    </Column>
    <Column field="licensePaid" header="Payée" sortable style="width: 8%">
      <template #body="slotProps">
        <ToggleSwitch
          :modelValue="slotProps.data.licensePaid"
          @update:modelValue="toggleLicense(slotProps.data)"
          :disabled="togglingIds.has(slotProps.data.id)"
        />
      </template>
    </Column>
    <Column header="Fichier licence" style="width: 17%">
      <template #body="slotProps">
        <div class="flex align-items-center gap-2">
          <Button
            :icon="uploadingIds.has(slotProps.data.id) ? 'pi pi-spin pi-spinner' : 'pi pi-upload'"
            severity="info"
            text
            rounded
            size="small"
            :disabled="uploadingIds.has(slotProps.data.id)"
            @click="triggerUpload(slotProps.data)"
            v-tooltip.top="'Importer licence'"
          />
          <Button
            v-if="slotProps.data.licenseFileName"
            icon="pi pi-download"
            severity="success"
            text
            rounded
            size="small"
            @click="downloadLicense(slotProps.data)"
            v-tooltip.top="'Télécharger licence'"
          />
          <Button
            v-if="slotProps.data.licenseFileName"
            icon="pi pi-trash"
            severity="danger"
            text
            rounded
            size="small"
            @click="deleteLicense(slotProps.data)"
            v-tooltip.top="'Supprimer licence'"
          />
          <Tag
            v-if="slotProps.data.licenseFileName"
            value="Fichier"
            severity="success"
            class="text-xs"
          />
          <span v-else class="text-color-secondary text-sm">-</span>
        </div>
      </template>
    </Column>
    <Column field="createdAt" header="Créé le" sortable style="width: 8%">
      <template #body="slotProps">
        {{ new Date(slotProps.data.createdAt).toLocaleDateString('fr-FR') }}
      </template>
    </Column>
    <Column header="Actions" style="width: 7%">
      <template #body="slotProps">
        <Button
          icon="pi pi-pencil"
          severity="secondary"
          text
          rounded
          @click="openDialog(slotProps.data)"
        />
      </template>
    </Column>
  </DataTable>

  <!-- Mobile : liste de cartes, le détail/édition passe par le dialog -->
  <div v-else class="member-cards">
    <template v-if="loading">
      <div v-for="i in 5" :key="i" class="member-card">
        <Skeleton shape="circle" size="3rem" />
        <div class="member-card__main">
          <Skeleton width="60%" height="1rem" class="mb-2" />
          <Skeleton width="40%" height="0.75rem" />
        </div>
      </div>
    </template>

    <template v-else-if="members.length">
      <button
        v-for="member in members"
        :key="member.id"
        type="button"
        class="member-card member-card--clickable"
        @click="openDialog(member)"
      >
        <MemberAvatar :member="member" size="large" />
        <div class="member-card__main">
          <span class="member-card__name">{{ member.firstName }} {{ member.lastName }}</span>
          <span class="member-card__meta">{{ memberTeamsLabel(member) }} · {{ member.phoneNumber }}</span>
          <div class="member-card__tags">
            <Tag
              :value="member.licensePaid ? 'Licence payée' : 'Non payée'"
              :severity="member.licensePaid ? 'success' : 'danger'"
              class="text-xs"
            />
            <Tag v-if="member.licenseFileName" value="Fichier" severity="secondary" class="text-xs" />
          </div>
        </div>
        <i class="pi pi-chevron-right member-card__chevron" />
      </button>
    </template>

    <div v-else class="member-cards__empty">
      <i class="pi pi-users" />
      <span>Aucun licencié trouvé</span>
    </div>

    <Paginator
      v-if="totalRecords > lazyParams.rows"
      :rows="lazyParams.rows"
      :totalRecords="totalRecords"
      :first="lazyParams.first"
      template="PrevPageLink CurrentPageReport NextPageLink"
      currentPageReportTemplate="{currentPage} / {totalPages}"
      @page="onPage"
    />
  </div>

  <input
    ref="fileInput"
    type="file"
    class="hidden"
    accept=".pdf,.jpg,.jpeg,.png"
    @change="handleFileSelected"
  />
</template>

<script setup lang="ts">
import type { DataTableSortEvent } from 'primevue/datatable';
import CreateUpdateMemberDialog from '~/components/dialogs/CreateUpdateMemberDialog.vue';
import MemberDetailsDialog from '~/components/dialogs/MemberDetailsDialog.vue';
import ConfirmDeleteDialog from '~/components/dialogs/ConfirmDeleteDialog.vue';
import MemberAvatar from '~/components/common/MemberAvatar.vue';
import { MemberRepository } from '~/repository/member-repository';
import type { Member } from '~/types/entity/Member';
import type { Team } from '~/types/entity/Team';

const props = defineProps<{
  teams: Team[]
}>();

const { show } = useDialogManager();
const memberRepository = new MemberRepository();
const members = ref<Member[]>([]);
const totalRecords = ref(0);
const loading = ref(false);
const togglingIds = ref(new Set<number>());
const uploadingIds = ref(new Set<number>());
const fileInput = ref<HTMLInputElement | null>(null);
const uploadTargetMember = ref<Member | null>(null);

const searchValue = ref('');
const selectedTeamId = ref<number | null>(null);
const licensePaidFilter = ref(false);
const hasLicenseFilter = ref(false);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const isMobile = useIsMobile();

const teamOptions = computed(() =>
  props.teams.map(t => ({ label: t.name, value: t.id }))
);

const memberTeamsLabel = (member: Member) =>
  (member.teams ?? []).map(t => t.name).join(' · ') || '—';

const lazyParams = ref({
  first: 0,
  rows: 10,
  sortField: 'firstName',
  sortOrder: 1 as 1 | -1,
});

watch(searchValue, () => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    lazyParams.value.first = 0;
    fetchData();
  }, 300);
});

watch([selectedTeamId, licensePaidFilter, hasLicenseFilter], () => {
  lazyParams.value.first = 0;
  fetchData();
});

const fetchData = async () => {
  loading.value = true;
  try {
    const result = await memberRepository.getPaginated({
      page: Math.floor(lazyParams.value.first / lazyParams.value.rows) + 1,
      limit: lazyParams.value.rows,
      sortField: lazyParams.value.sortField,
      sortOrder: lazyParams.value.sortOrder === 1 ? 'asc' : 'desc',
      search: searchValue.value || undefined,
      teamId: selectedTeamId.value || undefined,
      licensePaid: licensePaidFilter.value ? true : undefined,
      hasLicense: hasLicenseFilter.value ? true : undefined,
    });
    members.value = result.data;
    totalRecords.value = result.total;
  } finally {
    loading.value = false;
  }
};

// Partagé entre le DataTable (desktop) et le Paginator (mobile)
const onPage = (event: { first: number; rows: number }) => {
  lazyParams.value.first = event.first;
  lazyParams.value.rows = event.rows;
  fetchData();
};

const onSort = (event: DataTableSortEvent) => {
  lazyParams.value.sortField = event.sortField as string;
  lazyParams.value.sortOrder = event.sortOrder as 1 | -1;
  lazyParams.value.first = 0;
  fetchData();
};

const refresh = () => {
  fetchData();
};

defineExpose({ refresh });

const toggleLicense = async (member: Member) => {
  togglingIds.value.add(member.id);
  try {
    await memberRepository.toggleLicense(member.id);
    await fetchData();
  } finally {
    togglingIds.value.delete(member.id);
  }
};

const triggerUpload = (member: Member) => {
  if (member.licenseFileName) {
    show({
      component: ConfirmDeleteDialog,
      props: {
        header: 'Remplacer la licence',
        message: `Une licence existe déjà pour ${member.firstName} ${member.lastName}. Voulez-vous la remplacer ?`,
        confirmLabel: 'Remplacer',
        onConfirm: async () => {
          uploadTargetMember.value = member;
          fileInput.value?.click();
        }
      }
    });
  } else {
    uploadTargetMember.value = member;
    fileInput.value?.click();
  }
};

const handleFileSelected = async (event: Event) => {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  const member = uploadTargetMember.value;

  if (!file || !member) return;

  uploadingIds.value.add(member.id);
  try {
    await memberRepository.uploadLicense(member.id, file);
    await fetchData();
  } finally {
    uploadingIds.value.delete(member.id);
    uploadTargetMember.value = null;
    input.value = '';
  }
};

const onUploadProfilePicture = async (member: Member, file: File) => {
  await memberRepository.uploadProfilePicture(member.id, file);
  await fetchData();
};

const onDeleteProfilePicture = async (member: Member) => {
  await memberRepository.deleteProfilePicture(member.id);
  await fetchData();
};

const deleteLicense = (member: Member) => {
  show({
    component: ConfirmDeleteDialog,
    props: {
      message: `Êtes-vous sûr de vouloir supprimer la licence de ${member.firstName} ${member.lastName} ?`,
      onConfirm: async () => {
        await memberRepository.deleteLicense(member.id);
        await fetchData();
      }
    }
  });
};

const downloadLicense = async (member: Member) => {
  const config = useRuntimeConfig();
  const url = `${config.public.apiBase}/member/${member.id}/download-license`;
  const token = useCookie('auth_token').value;

  const res = await fetch(url, {
    headers: { Authorization: `Bearer ${token}` }
  });
  const blob = await res.blob();
  const blobUrl = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = blobUrl;
  a.download = member.licenseFileName || 'licence';
  a.click();
  URL.revokeObjectURL(blobUrl);
};

const openDialog = (member?: Member) => {
  if (member) {
    show({
      component: MemberDetailsDialog,
      props: {
        member,
        teams: props.teams,
        onSaved: () => fetchData(),
      }
    });
  } else {
    show({
      component: CreateUpdateMemberDialog,
      props: {
        member: null,
        teams: props.teams,
        onSaved: () => fetchData()
      }
    });
  }
};

onMounted(() => {
  fetchData();
});
</script>

<style scoped>
/* Filtres */
.members-filters {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.members-filters__search {
  flex: 1 1 16rem;
}

.members-filters__team {
  width: 15rem;
}

.members-filters__toggles {
  display: flex;
  align-items: center;
  gap: 1rem;
}

@media (max-width: 767px) {
  .members-filters__search {
    flex: 1 1 100%;
  }

  .members-filters__team {
    flex: 1 1 100%;
    width: auto;
  }

  .members-filters__toggles {
    flex-wrap: wrap;
    row-gap: 0.5rem;
  }
}

/* Cartes mobile */
.member-cards {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.member-card {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem;
  border: 1px solid var(--p-surface-border);
  border-radius: 10px;
  background: var(--p-surface-card);
  text-align: left;
  width: 100%;
}

.member-card--clickable {
  cursor: pointer;
  font: inherit;
  color: inherit;
  transition: background-color 0.15s, border-color 0.15s;
}

.member-card--clickable:active {
  background: var(--p-surface-hover);
}

.member-card__main {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  flex: 1;
  min-width: 0;
}

.member-card__name {
  font-weight: 600;
  color: var(--p-text-color);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.member-card__meta {
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.member-card__tags {
  display: flex;
  gap: 0.375rem;
  flex-wrap: wrap;
  margin-top: 0.15rem;
}

.member-card__chevron {
  color: var(--p-text-muted-color);
  font-size: 0.8rem;
  flex-shrink: 0;
}

.member-cards__empty,
.datatable-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  padding: 2.5rem 1rem;
  color: var(--p-text-muted-color);
}

.member-cards__empty i,
.datatable-empty i {
  font-size: 2rem;
  opacity: 0.5;
}
</style>
