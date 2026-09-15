<template>
  <div class="teams">
    <header class="teams__head">
      <div class="teams__stats">
        <div class="team-stat">
          <span class="team-stat__value">{{ teams.length }}</span>
          <span class="team-stat__label">équipes</span>
        </div>
        <div class="team-stat">
          <span class="team-stat__value">{{ totalMembers }}</span>
          <span class="team-stat__label">licenciés affectés</span>
        </div>
        <div class="team-stat" :class="{ 'team-stat--alert': unpaid > 0 }">
          <span class="team-stat__value">{{ unpaid }}</span>
          <span class="team-stat__label">licences non payées</span>
        </div>
        <div class="team-stat" :class="{ 'team-stat--alert': withoutCoach > 0 }">
          <span class="team-stat__value">{{ withoutCoach }}</span>
          <span class="team-stat__label">sans coach</span>
        </div>
      </div>

      <div class="teams__actions">
        <IconField class="teams__search">
          <InputIcon class="pi pi-search" />
          <InputText v-model="search" placeholder="Rechercher une équipe ou un coach..." class="w-full" />
        </IconField>
        <Button label="Nouvelle équipe" icon="pi pi-plus" @click="openCreateDialog" />
      </div>
    </header>

    <SkeletonLoader v-if="loading" type="table" />

    <!-- Liste unique, du téléphone au desktop : une ligne = une équipe, le
         détail vit sur sa propre page plutôt que dans une ligne dépliée. -->
    <ul v-else-if="filteredTeams.length" class="team-list">
      <li v-for="team in filteredTeams" :key="team.id">
        <NuxtLink :to="`/dashboard/settings/teams/${team.id}`" class="team-row">
          <div class="team-row__main">
            <span class="team-row__name">{{ team.name }}</span>
            <span class="team-row__coaches">
              <template v-if="team.coaches?.length">
                <i class="pi pi-user" /> {{ team.coaches.map(c => c.email).join(', ') }}
              </template>
              <Tag v-else value="Aucun coach" severity="warn" class="text-xs" />
            </span>
          </div>

          <div class="team-row__counts">
            <span class="team-row__members">{{ team.memberCount ?? 0 }}</span>
            <span class="team-row__label">licenciés</span>
            <div class="team-row__gauge" :title="`${team.paidCount ?? 0} licence(s) payée(s)`">
              <span :style="{ width: paidRatio(team) + '%' }" />
            </div>
            <span class="team-row__label">{{ team.paidCount ?? 0 }}/{{ team.memberCount ?? 0 }} payées</span>
          </div>

          <i class="pi pi-chevron-right team-row__chevron" />
        </NuxtLink>
      </li>
    </ul>

    <div v-else class="teams__empty">
      <i class="pi pi-sitemap" />
      <span>{{ search ? 'Aucune équipe ne correspond' : 'Aucune équipe' }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import SkeletonLoader from '~/components/common/skeleton/SkeletonLoader.vue';
import CreateUpdateTeamDialog from '~/components/dialogs/CreateUpdateTeamDialog.vue';
import { TeamRepository } from '~/repository/team-repository';
import { UserRepository } from '~/repository/user-repository';
import type { Team } from '~/types/entity/Team';
import { AppUserRole } from '~/types/entity/AppUser';

definePageMeta({
  middleware: 'auth-middleware',
  layout: 'dashboard',
  requiredRoles: [AppUserRole.SUPER_ADMIN],
  redirectTo: '/dashboard/calendar'
});

useHead({ title: 'Équipes' });

const { show } = useDialogManager();
const teamRepository = new TeamRepository();
const { selected: season, load: loadSeasons } = useSeasonFilter();

const teams = ref<Team[]>([]);
const search = ref('');
const loading = ref(true);

// Les compteurs d'effectif sont scopés à la saison : on recharge à chaque bascule.
const reload = async () => {
  teams.value = await teamRepository.getAll(season.value || undefined);
};

const filteredTeams = computed(() => {
  const needle = search.value.trim().toLowerCase();
  if (!needle) return teams.value;

  return teams.value.filter(t =>
    t.name.toLowerCase().includes(needle)
    || (t.coaches ?? []).some(c => c.email.toLowerCase().includes(needle))
  );
});

const paidRatio = (team: Team) =>
  team.memberCount ? Math.round(((team.paidCount ?? 0) / team.memberCount) * 100) : 0;

const totalMembers = computed(() => teams.value.reduce((n, t) => n + (t.memberCount ?? 0), 0));
const unpaid = computed(() =>
  teams.value.reduce((n, t) => n + ((t.memberCount ?? 0) - (t.paidCount ?? 0)), 0)
);
const withoutCoach = computed(() => teams.value.filter(t => !t.coaches?.length).length);

const openCreateDialog = async () => {
  const users = await new UserRepository().getAll();

  show({
    component: CreateUpdateTeamDialog,
    props: {
      team: null,
      users,
      onSubmit: async (values: { name: string; userIds: number[] }) => {
        const created = await teamRepository.createUpdate(values.name, null, values.userIds);
        await navigateTo(`/dashboard/settings/teams/${created.id}`);
      }
    }
  });
};

watch(season, reload);

// La saison doit être connue avant le premier chargement : sinon les compteurs
// partent sur la saison courante puis se rescopent.
onMounted(async () => {
  try {
    await loadSeasons();
    await reload();
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.teams {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.teams__head {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.teams__stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(8rem, 1fr));
  gap: 0.75rem;
}

.team-stat {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  padding: 0.75rem 1rem;
  border: 1px solid var(--p-surface-border);
  border-radius: 10px;
  background: var(--p-surface-card);
}

.team-stat--alert {
  border-color: var(--p-orange-400, #fb923c);
}

.team-stat__value {
  font-size: 1.5rem;
  font-weight: 700;
  line-height: 1.1;
  color: var(--p-text-color);
}

.team-stat__label {
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
}

.teams__actions {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  flex-wrap: wrap;
}

.teams__search {
  flex: 1 1 16rem;
}

.team-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.team-row {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.85rem 1rem;
  border: 1px solid var(--p-surface-border);
  border-radius: 10px;
  background: var(--p-surface-card);
  color: inherit;
  text-decoration: none;
  transition: border-color 0.15s, background-color 0.15s;
}

.team-row:hover {
  border-color: var(--p-primary-color);
  background: var(--p-surface-hover);
}

.team-row__main {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  flex: 1;
  min-width: 0;
}

.team-row__name {
  font-weight: 600;
  font-size: 1rem;
  color: var(--p-text-color);
}

.team-row__coaches {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.team-row__counts {
  display: grid;
  grid-template-columns: auto auto;
  align-items: baseline;
  gap: 0 0.35rem;
  flex-shrink: 0;
  text-align: right;
}

.team-row__members {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--p-text-color);
}

.team-row__label {
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
}

.team-row__gauge {
  grid-column: 1 / -1;
  height: 4px;
  width: 100%;
  border-radius: 999px;
  background: var(--p-surface-300, #e5e7eb);
  overflow: hidden;
}

.team-row__gauge span {
  display: block;
  height: 100%;
  background: var(--p-green-500, #22c55e);
}

.team-row__chevron {
  color: var(--p-text-muted-color);
  font-size: 0.8rem;
  flex-shrink: 0;
}

.teams__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  padding: 3rem 1rem;
  color: var(--p-text-muted-color);
}

.teams__empty i {
  font-size: 2rem;
  opacity: 0.5;
}

@media (max-width: 767px) {
  .team-row__coaches {
    max-width: 55vw;
  }

  .teams__search {
    flex: 1 1 100%;
  }
}
</style>
