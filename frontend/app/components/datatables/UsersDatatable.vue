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
    v-if="!isMobile"
    :value="users"
    :loading="loading"
    lazy
    stripedRows
    tableStyle="min-width: 48rem"
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
    <template #empty>
      <div class="datatable-empty">
        <i class="pi pi-users" />
        <span>Aucun utilisateur trouvé</span>
      </div>
    </template>
    <Column field="member.name" header="Licencié" sortable style="width: 25%">
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

  <!-- Mobile : liste de cartes -->
  <div v-else class="user-cards">
    <template v-if="loading">
      <div v-for="i in 5" :key="i" class="user-card">
        <Skeleton width="50%" height="1rem" class="mb-2" />
        <Skeleton width="70%" height="0.8rem" />
      </div>
    </template>

    <template v-else-if="users.length">
      <div v-for="user in users" :key="user.id" class="user-card">
        <div class="user-card__row">
          <UserMemberCard
            :user="user"
            :on-link="(memberId: number) => handleLinkMember(user.id, memberId)"
            :on-unlink="() => handleUnlinkMember(user.id)"
          />
          <Button
            icon="pi pi-pencil"
            severity="secondary"
            text
            rounded
            @click="openDialog(user)"
          />
        </div>
        <div class="user-card__row user-card__row--meta">
          <span class="user-card__email">{{ user.email }}</span>
          <RoleBadge :role="user.roles[0] ?? ''" size="xs" />
        </div>
      </div>
    </template>

    <div v-else class="user-cards__empty">
      <i class="pi pi-users" />
      <span>Aucun utilisateur trouvé</span>
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
</template>

<script setup lang="ts">
import type { DataTableSortEvent } from 'primevue/datatable';
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

const isMobile = useIsMobile();

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

<style scoped>
.datatable-empty,
.user-cards__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  padding: 2.5rem 1rem;
  color: var(--p-text-muted-color);
}

.datatable-empty i,
.user-cards__empty i {
  font-size: 2rem;
  opacity: 0.5;
}

/* Cartes mobile */
.user-cards {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.user-card {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  padding: 0.75rem;
  border: 1px solid var(--p-surface-border);
  border-radius: 10px;
  background: var(--p-surface-card);
}

.user-card__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  min-width: 0;
}

.user-card__row--meta {
  padding-left: 0.25rem;
}

.user-card__email {
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
}
</style>
