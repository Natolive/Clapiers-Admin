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
    <SelectButton
      v-model="licensePaidFilter"
      :options="licensePaidOptions"
      option-label="label"
      option-value="value"
      :allow-empty="false"
      aria-label="Filtre licence payée"
      class="members-filters__paid"
    />
    <Select
      v-model="fsgtFilter"
      :options="fsgtOptions"
      option-label="label"
      option-value="value"
      placeholder="Inscription FSGT"
      class="members-filters__fsgt"
    />
  </div>

  <div v-if="selectedMembers.length" class="members-selection mb-3">
    <i class="pi pi-check-square" />
    <span>{{ selectedMembers.length }} licencié(s) sélectionné(s) — l'export ne portera que sur eux.</span>
    <Button label="Tout désélectionner" size="small" severity="secondary" text @click="selectedMembers = []" />
  </div>

  <DataTable
    v-if="!isMobile"
    v-model:selection="selectedMembers"
    :value="members"
    :loading="loading"
    lazy
    stripedRows
    dataKey="id"
    tableStyle="min-width: 60rem"
    class="p-datatable-sm members-table"
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
    <Column selectionMode="multiple" headerStyle="width: 3rem" :exportable="false" />
    <Column header="Licencié" sortable field="firstName" style="width: 20%">
      <template #body="slotProps">
        <div class="flex align-items-center gap-2">
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
        <LicensePaidTag :paid="slotProps.data.licensePaid" />
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
    <Column header="FSGT" style="width: 11%">
      <template #body="slotProps">
        <!--
          La case n'est qu'un affichage : elle ne pilote jamais sa propre valeur,
          sinon annuler le dialog la laisserait cochée à tort (PrimeVue garde un
          état interne). `readonly` la fige, c'est la cellule qui porte le clic,
          et seul un aller-retour serveur réussi change ce qu'on voit.
        -->
        <div
          class="fsgt-cell"
          role="button"
          tabindex="0"
          :aria-pressed="slotProps.data.fsgtRegistered ? 'true' : 'false'"
          :aria-label="`Inscription FSGT de ${slotProps.data.firstName} ${slotProps.data.lastName}`"
          @click="openFsgtDialog(slotProps.data)"
          @keydown.enter.prevent="openFsgtDialog(slotProps.data)"
          @keydown.space.prevent="openFsgtDialog(slotProps.data)"
        >
          <Checkbox :model-value="slotProps.data.fsgtRegistered" binary readonly tabindex="-1" />
          <span v-if="slotProps.data.seasonLicenseNumber" class="fsgt-cell__number">
            {{ slotProps.data.seasonLicenseNumber }}
          </span>
          <span v-else class="text-color-secondary text-sm">—</span>
        </div>
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
            size="small"
            @click="openDialog(slotProps.data)"
            v-tooltip.top="'Modifier'"
          />
          <Button
            v-if="isSuperAdmin"
            icon="pi pi-folder-open"
            severity="secondary"
            text
            rounded
            size="small"
            @click="openDialog(slotProps.data, 'media')"
            v-tooltip.top="'Médiathèque'"
          />
          <Button
            v-if="isSuperAdmin"
            icon="pi pi-trash"
            severity="danger"
            text
            rounded
            size="small"
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
        <Skeleton shape="circle" size="2.25rem" />
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
        <MemberAvatar :member="member" size="normal" />
        <div class="member-card__main">
          <span class="member-card__name">{{ member.firstName }} {{ member.lastName }}</span>
          <span class="member-card__meta">{{ memberTeamsLabel(member) }} · {{ member.phoneNumber }}</span>
          <div class="member-card__tags">
            <LicensePaidTag :paid="member.licensePaid" />
            <Tag v-if="member.hasLicenseDocument" value="Licence" severity="secondary" class="text-xs" />
            <Tag
              :value="member.fsgtRegistered ? 'FSGT' : 'Hors FSGT'"
              :severity="member.fsgtRegistered ? 'success' : 'warn'"
              class="text-xs"
              @click.stop="openFsgtDialog(member)"
            />
          </div>
        </div>
        <Button
          v-if="isSuperAdmin"
          icon="pi pi-folder-open"
          severity="secondary"
          text
          rounded
          size="small"
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
          size="small"
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
import FsgtRegistrationDialog from '~/components/dialogs/FsgtRegistrationDialog.vue';
import MemberDetailsDialog from '~/components/dialogs/MemberDetailsDialog.vue';
import MemberAvatar from '~/components/common/MemberAvatar.vue';
import LicensePaidTag from '~/components/common/LicensePaidTag.vue';
import { MemberRepository } from '~/repository/member-repository';
import type { Member } from '~/types/entity/Member';
import type { Team } from '~/types/entity/Team';
import { LicensePaidFilter } from '~/types/enum/LicensePaidFilter';

const props = defineProps<{
  teams: Team[]
}>();

const { show } = useDialogManager();
const { isSuperAdmin } = useUserRole();
const memberRepository = new MemberRepository();
const members = ref<Member[]>([]);
const totalRecords = ref(0);
const loading = ref(false);

// Filtres, pagination et tri vivent dans l'URL : recharger la page ou partager
// le lien redonne la même vue.
const route = useRoute();
const router = useRouter();
const query = route.query;

const searchValue = ref(String(query.search ?? ''));
const selectedTeamId = ref<number | null>(query.teamId ? Number(query.teamId) : null);
// 3 états : tous / payée / impayée (undefined côté API = pas de filtre),
// initialisés depuis ?licensePaid= pour les liens du tableau de bord.
const licensePaidOptions = [
  { label: 'Tous', value: LicensePaidFilter.ALL },
  { label: 'Payée', value: LicensePaidFilter.PAID },
  { label: 'Impayée', value: LicensePaidFilter.UNPAID },
];
// Inscription FSGT : 3 états, comme le filtre « payée ». La valeur undefined
// n'étant pas sélectionnable dans un Select, on passe par une chaîne.
const fsgtOptions = [
  { label: 'Inscription FSGT : tous', value: 'all' },
  { label: 'Inscrits FSGT', value: 'yes' },
  { label: 'Non inscrits FSGT', value: 'no' },
];
const fsgtFilter = ref<'all' | 'yes' | 'no'>(
  query.fsgt === 'yes' || query.fsgt === 'no' ? query.fsgt : 'all',
);
const fsgtRegisteredParam = computed(() =>
  fsgtFilter.value === 'all' ? undefined : fsgtFilter.value === 'yes',
);

const selectedMembers = ref<Member[]>([]);

const queryPaid = String(query.licensePaid ?? '') as LicensePaidFilter;
const licensePaidFilter = ref<LicensePaidFilter>(
  Object.values(LicensePaidFilter).includes(queryPaid) ? queryPaid : LicensePaidFilter.ALL,
);
const { selected: season, load: loadSeasons } = useSeasonFilter();
// La saison est un état partagé entre les vues : l'URL a le dernier mot ici.
if (query.season) season.value = String(query.season);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const isMobile = useIsMobile();

const teamOptions = computed(() =>
  props.teams.map(t => ({ label: t.name, value: t.id }))
);

const memberTeamsLabel = (member: Member) =>
  (member.teams ?? []).map(t => t.name).join(' · ') || '—';

const rowsPerPage = Number(query.rows) || 10;
const sortQuery = String(query.sort ?? 'firstName');
const lazyParams = ref({
  first: (Math.max(1, Number(query.page) || 1) - 1) * rowsPerPage,
  rows: rowsPerPage,
  sortField: sortQuery.replace(/^-/, ''),
  sortOrder: (sortQuery.startsWith('-') ? -1 : 1) as 1 | -1,
});

watch([searchValue, selectedTeamId, licensePaidFilter, fsgtFilter, season, lazyParams], () => {
  const page = Math.floor(lazyParams.value.first / lazyParams.value.rows) + 1;
  const next: Record<string, string | undefined> = {
    search: searchValue.value || undefined,
    teamId: selectedTeamId.value ? String(selectedTeamId.value) : undefined,
    licensePaid: licensePaidFilter.value === LicensePaidFilter.ALL ? undefined : licensePaidFilter.value,
    fsgt: fsgtFilter.value === 'all' ? undefined : fsgtFilter.value,
    season: season.value || undefined,
    page: page > 1 ? String(page) : undefined,
    rows: lazyParams.value.rows === 10 ? undefined : String(lazyParams.value.rows),
    sort: lazyParams.value.sortField === 'firstName' && lazyParams.value.sortOrder === 1
      ? undefined
      : `${lazyParams.value.sortOrder === -1 ? '-' : ''}${lazyParams.value.sortField}`,
  };
  router.replace({ query: Object.fromEntries(Object.entries(next).filter(([, v]) => v !== undefined)) });
}, { deep: true });

watch(searchValue, () => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    lazyParams.value.first = 0;
    fetchData();
  }, 300);
});

watch([selectedTeamId, licensePaidFilter, season, fsgtFilter], () => {
  lazyParams.value.first = 0;
  // La sélection porte sur des lignes qui ne sont peut-être plus dans la
  // liste : la vider évite d'exporter des gens qu'on ne voit plus.
  selectedMembers.value = [];
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
      licensePaid: licensePaidFilter.value === LicensePaidFilter.ALL ? undefined : licensePaidFilter.value === LicensePaidFilter.PAID,
      season: season.value || undefined,
      fsgtRegistered: fsgtRegisteredParam.value,
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

// Les filtres vivent ici, mais le bouton d'export est dans la toolbar de la
// page : on les expose pour que l'export porte sur ce qui est affiché.
const currentFilters = computed(() => ({
  search: searchValue.value || undefined,
  teamId: selectedTeamId.value || undefined,
  licensePaid: licensePaidFilter.value === LicensePaidFilter.ALL
    ? undefined
    : licensePaidFilter.value === LicensePaidFilter.PAID,
  season: season.value || undefined,
  fsgtRegistered: fsgtRegisteredParam.value,
}));

const selectedMemberIds = computed(() => selectedMembers.value.map(m => m.id));

const currentTeamName = computed(() =>
  props.teams.find(t => t.id === selectedTeamId.value)?.name,
);

defineExpose({ refresh, currentFilters, currentTeamName, selectedMemberIds });

// Cocher la case n'écrit rien directement : le numéro de licence est requis
// pour déclarer l'inscription, donc on passe par le dialog.
const openFsgtDialog = (member: Member) => {
  show({
    component: FsgtRegistrationDialog,
    props: {
      member,
      season: season.value,
      registered: member.fsgtRegistered ?? false,
      onSaved: () => fetchData(),
    },
  });
};

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

@media (max-width: 767px) {
  .members-filters__search {
    flex: 1 1 100%;
  }

  .members-filters__team {
    flex: 1 1 100%;
    width: auto;
  }

  .members-filters__paid {
    flex: 1 1 100%;
  }

  .members-filters__fsgt {
    flex: 1 1 100%;
    width: auto;
  }

  .members-filters__paid :deep(.p-togglebutton) {
    flex: 1;
  }
}

.members-filters__fsgt {
  width: 15rem;
}

/* Table dense : plus de lignes visibles sans scroller. */
.members-table :deep(.p-datatable-thead > tr > th),
.members-table :deep(.p-datatable-tbody > tr > td) {
  padding: 0.35rem 0.5rem;
  font-size: 0.85rem;
}

.members-table :deep(.p-avatar) {
  width: 1.75rem;
  height: 1.75rem;
  font-size: 0.7rem;
}

.members-table :deep(.p-tag) {
  padding: 0.05rem 0.35rem;
}

.members-table :deep(.p-button.p-button-icon-only) {
  width: 1.75rem;
  height: 1.75rem;
}

.members-selection {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  border-radius: 8px;
  background: var(--p-surface-hover);
  font-size: 0.875rem;
}

.fsgt-cell {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  border-radius: 6px;
  padding: 0.25rem;
  margin: -0.25rem;
}

.fsgt-cell:hover,
.fsgt-cell:focus-visible {
  background: var(--p-surface-hover);
}

.fsgt-cell__number {
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Cartes mobile */
.member-cards {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.member-card :deep(.p-button.p-button-icon-only) {
  width: 1.75rem;
  height: 1.75rem;
}

.member-card {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.625rem;
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
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--p-text-color);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.member-card__meta {
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.member-card__tags {
  display: flex;
  gap: 0.25rem;
  flex-wrap: wrap;
  margin-top: 0.1rem;
}

.member-card__tags :deep(.p-tag) {
  padding: 0.05rem 0.35rem;
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
