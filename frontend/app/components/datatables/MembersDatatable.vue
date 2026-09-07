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
            v-for="team in slotProps.data.teams ?? []"
            :key="team.id"
            :value="team.name"
            severity="secondary"
            class="text-xs"
          />
        </div>
      </template>
    </Column>
    <Column header="Payée" style="width: 8%">
      <template #body="slotProps">
        <Tag
          :value="slotProps.data.licensePaid ? 'Payée' : 'Non payée'"
          :severity="slotProps.data.licensePaid ? 'success' : 'danger'"
          class="text-xs"
        />
      </template>
    </Column>
    <Column header="Licence" style="width: 10%">
      <template #body="slotProps">
        <Tag
          v-if="slotProps.data.hasLicenseDocument"
          icon="pi pi-check"
          value="Présente"
          severity="success"
          class="text-xs"
        />
        <span v-else class="text-color-secondary text-sm">Aucune</span>
      </template>
    </Column>
    <Column field="createdAt" header="Créé le" sortable style="width: 8%">
      <template #body="slotProps">
        {{ new Date(slotProps.data.createdAt).toLocaleDateString('fr-FR') }}
      </template>
    </Column>
    <Column header="Actions" style="width: 10%">
      <template #body="slotProps">
        <div class="flex align-items-center">
          <Button
            icon="pi pi-pencil"
            severity="secondary"
            text
            rounded
            @click="openDialog(slotProps.data)"
            v-tooltip.top="'Modifier'"
          />
          <Button
            v-if="isSuperAdmin"
            icon="pi pi-folder-open"
            severity="secondary"
            text
            rounded
            @click="openDialog(slotProps.data, 'media')"
            v-tooltip.top="'Médiathèque'"
          />
          <Button
            v-if="isSuperAdmin"
            icon="pi pi-trash"
            severity="danger"
            text
            rounded
            @click="confirmDelete(slotProps.data)"
            v-tooltip.top="'Supprimer'"
          />
        </div>
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
      <div
        v-for="member in members"
        :key="member.id"
        role="button"
        tabindex="0"
        class="member-card member-card--clickable"
        @click="openDialog(member)"
        @keydown.enter="openDialog(member)"
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
            <Tag v-if="member.hasLicenseDocument" value="Licence" severity="secondary" class="text-xs" />
          </div>
        </div>
        <Button
          v-if="isSuperAdmin"
          icon="pi pi-folder-open"
          severity="secondary"
          text
          rounded
          class="member-card__media"
          @click.stop="openDialog(member, 'media')"
          v-tooltip.left="'Médiathèque'"
        />
        <Button
          v-if="isSuperAdmin"
          icon="pi pi-trash"
          severity="danger"
          text
          rounded
          @click.stop="confirmDelete(member)"
          v-tooltip.left="'Supprimer'"
        />
        <i class="pi pi-chevron-right member-card__chevron" />
      </div>
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

</template>

<script setup lang="ts">
import type { DataTableSortEvent } from 'primevue/datatable';
import ConfirmDeleteDialog from '~/components/dialogs/ConfirmDeleteDialog.vue';
import CreateUpdateMemberDialog from '~/components/dialogs/CreateUpdateMemberDialog.vue';
import MemberDetailsDialog from '~/components/dialogs/MemberDetailsDialog.vue';
import MemberAvatar from '~/components/common/MemberAvatar.vue';
import { MemberRepository } from '~/repository/member-repository';
import type { Member } from '~/types/entity/Member';
import type { Team } from '~/types/entity/Team';

const props = defineProps<{
  teams: Team[]
}>();

const { show } = useDialogManager();
const { isSuperAdmin } = useUserRole();
const memberRepository = new MemberRepository();
const members = ref<Member[]>([]);
const totalRecords = ref(0);
const loading = ref(false);
const searchValue = ref('');
const selectedTeamId = ref<number | null>(null);
const licensePaidFilter = ref(false);
const { selected: season, load: loadSeasons } = useSeasonFilter();
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

watch([selectedTeamId, licensePaidFilter, season], () => {
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
      season: season.value || undefined,
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

const openDialog = (member?: Member, initialTab: 'fiche' | 'media' = 'fiche') => {
  if (member) {
    show({
      component: MemberDetailsDialog,
      props: {
        member,
        teams: props.teams,
        initialTab,
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

// Suppression douce : la fiche sort des listes mais rien n'est détruit. Le
// message le dit, sinon on effraie l'utilisateur pour une action réversible.
const confirmDelete = (member: Member) => {
  show({
    component: ConfirmDeleteDialog,
    props: {
      header: 'Supprimer le licencié',
      message: `Retirer ${member.firstName} ${member.lastName} des licenciés ?`
        + ' Ses licences, paiements et documents sont conservés en base.',
      onConfirm: async () => {
        await memberRepository.delete(member.id);
        fetchData();
      },
    },
  });
};

// La saison doit être connue avant le premier fetch : sinon la liste part
// non filtrée puis se rescope, en affichant brièvement d'autres saisons.
onMounted(async () => {
  await loadSeasons();
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
