<template>
  <div class="flex gap-3 mb-3">
    <IconField class="flex-1">
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
      class="w-15rem"
    />
    <div class="flex align-items-center gap-2">
      <ToggleSwitch v-model="licensePaidFilter" />
      <span class="white-space-nowrap">Licence payée</span>
    </div>
    <div class="flex align-items-center gap-2">
      <ToggleSwitch v-model="hasLicenseFilter" />
      <span class="white-space-nowrap">Fichier licence</span>
    </div>
  </div>

  <DataTable
    :value="members"
    :loading="loading"
    lazy
    stripedRows
    responsiveLayout="scroll"
    class="p-datatable-sm"
    paginator
    :rows="lazyParams.rows"
    :totalRecords="totalRecords"
    :rowsPerPageOptions="[10, 25, 50]"
    paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
    currentPageReportTemplate="{first} à {last} sur {totalRecords} membres"
    :first="lazyParams.first"
    :sortField="lazyParams.sortField"
    :sortOrder="lazyParams.sortOrder"
    @page="onPage"
    @sort="onSort"
  >
    <Column header="Membre" sortable field="firstName" style="width: 20%">
      <template #body="slotProps">
        <div class="flex align-items-center gap-3">
          <MemberAvatar :member="slotProps.data" size="normal" />
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
    <Column field="team.name" header="Équipe" sortable style="width: 10%"></Column>
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

  <input
    ref="fileInput"
    type="file"
    class="hidden"
    accept=".pdf,.jpg,.jpeg,.png"
    @change="handleFileSelected"
  />
</template>

<script setup lang="ts">
import type { DataTablePageEvent, DataTableSortEvent } from 'primevue/datatable';
import CreateUpdateMemberDialog from '~/components/dialogs/CreateUpdateMemberDialog.vue';
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

const teamOptions = computed(() =>
  props.teams.map(t => ({ label: t.name, value: t.id }))
);

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

watch(selectedTeamId, () => {
  lazyParams.value.first = 0;
  fetchData();
});

watch(licensePaidFilter, () => {
  lazyParams.value.first = 0;
  fetchData();
});

watch(hasLicenseFilter, () => {
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

const onPage = (event: DataTablePageEvent) => {
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
  show({
    component: CreateUpdateMemberDialog,
    props: {
      member: member || null,
      teams: props.teams,
      onSubmit: async (values: { firstName: string; lastName: string; phoneNumber: string; email: string; teamId: number }) => {
        await memberRepository.createUpdate(
          values.firstName,
          values.lastName,
          values.phoneNumber,
          values.email,
          values.teamId,
          member?.id || null
        );
        await fetchData();
      }
    }
  });
};

onMounted(() => {
  fetchData();
});
</script>
