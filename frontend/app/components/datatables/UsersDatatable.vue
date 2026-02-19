<template>
  <div class="flex gap-3 mb-3">
    <IconField class="flex-1">
      <InputIcon class="pi pi-search" />
      <InputText
        v-model="searchValue"
        placeholder="Rechercher par nom, prénom ou email..."
        class="w-full"
      />
    </IconField>
  </div>

  <DataTable
    :value="users"
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
    currentPageReportTemplate="{first} à {last} sur {totalRecords} utilisateurs"
    :first="lazyParams.first"
    :sortField="lazyParams.sortField"
    :sortOrder="lazyParams.sortOrder"
    @page="onPage"
    @sort="onSort"
  >
    <Column field="member.name" header="Membre" sortable style="width: 25%">
      <template #body="slotProps">
        <UserMemberCard
          :user="slotProps.data"
          :on-link="(memberId: number) => handleLinkMember(slotProps.data.id, memberId)"
          :on-unlink="() => handleUnlinkMember(slotProps.data.id)"
        />
      </template>
    </Column>
    <Column field="email" header="Email" sortable style="width: 40%"></Column>
    <Column field="roles" header="Rôle" style="width: 20%">
      <template #body="slotProps">
        <RoleBadge
          :role="slotProps.data.roles[0]"
          size="xs"
        />
      </template>
    </Column>
    <Column header="Actions" style="width: 5%">
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
</template>

<script setup lang="ts">
import type { DataTablePageEvent, DataTableSortEvent } from 'primevue/datatable';
import CreateUpdateUserDialog from '~/components/dialogs/CreateUpdateUserDialog.vue';
import UserMemberCard from '~/components/users/UserMemberCard.vue';
import type { AppUser, AppUserRole } from '~/types/entity/AppUser';
import RoleBadge from '../badge/RoleBadge.vue';
import { UserRepository } from '~/repository/user-repository';

const { show } = useDialogManager();
const userRepository = new UserRepository();
const users = ref<AppUser[]>([]);
const totalRecords = ref(0);
const loading = ref(false);

const searchValue = ref('');
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const lazyParams = ref({
  first: 0,
  rows: 10,
  sortField: 'member.name',
  sortOrder: 1 as 1 | -1,
});

watch(searchValue, () => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    lazyParams.value.first = 0;
    fetchData();
  }, 300);
});

const fetchData = async () => {
  loading.value = true;
  try {
    const result = await userRepository.getPaginated({
      page: Math.floor(lazyParams.value.first / lazyParams.value.rows) + 1,
      limit: lazyParams.value.rows,
      sortField: lazyParams.value.sortField,
      sortOrder: lazyParams.value.sortOrder === 1 ? 'asc' : 'desc',
      search: searchValue.value || undefined,
    });
    users.value = result.data;
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

const handleLinkMember = async (userId: number, memberId: number) => {
  await userRepository.linkMember(userId, memberId);
  await fetchData();
};

const handleUnlinkMember = async (userId: number) => {
  await userRepository.unlinkMember(userId);
  await fetchData();
};

const openDialog = (user?: AppUser) => {
  show({
    component: CreateUpdateUserDialog,
    props: {
      user: user || null,
      onSubmit: async (values: { email: string; role: AppUserRole; password: string | null }) => {
        await userRepository.createUpdate(
          values.email,
          values.role,
          values.password,
          user?.id || null,
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
