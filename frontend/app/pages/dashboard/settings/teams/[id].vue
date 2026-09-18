<template>
  <div class="team-page">
    <NuxtLink to="/dashboard/settings/teams" class="team-back">
      <i class="pi pi-arrow-left" /> Équipes
    </NuxtLink>

    <SkeletonLoader v-if="loading" type="table" />

    <div v-else-if="!team" class="team-missing">
      <i class="pi pi-exclamation-triangle" />
      <span>Cette équipe n'existe pas ou a été supprimée.</span>
      <Button label="Retour aux équipes" text @click="navigateTo('/dashboard/settings/teams')" />
    </div>

    <template v-else>
      <header class="team-header">
        <div class="team-header__identity">
          <h1 class="team-header__name">{{ team.name }}</h1>
          <div class="team-header__coaches">
            <Tag
              v-for="coach in team.coaches ?? []"
              :key="coach.id"
              :value="coach.email"
              severity="secondary"
              class="text-xs"
            />
            <Tag v-if="!team.coaches?.length" value="Aucun coach" severity="warn" class="text-xs" />
          </div>
        </div>

        <div class="team-header__actions">
          <Button label="Renommer" icon="pi pi-pencil" severity="secondary" outlined size="small" @click="openEditDialog" />
          <Button label="Supprimer" icon="pi pi-trash" severity="danger" outlined size="small" @click="confirmDelete" />
        </div>
      </header>

      <div class="team-metrics">
        <div class="team-metric">
          <span class="team-metric__value">{{ members.length }}</span>
          <span class="team-metric__label">licenciés {{ season }}</span>
        </div>
        <div class="team-metric" :class="{ 'team-metric--alert': unpaid > 0 }">
          <span class="team-metric__value">{{ unpaid }}</span>
          <span class="team-metric__label">licences impayées</span>
        </div>
        <div class="team-metric">
          <span class="team-metric__value">{{ games.length }}</span>
          <span class="team-metric__label">matchs</span>
        </div>
      </div>

      <div class="team-tabs">
        <button
          v-for="t in tabs"
          :key="t.key"
          type="button"
          class="team-tab"
          :class="{ 'team-tab--active': tab === t.key }"
          @click="tab = t.key"
        >
          <i :class="t.icon" /> {{ t.label }}
          <span v-if="t.count !== undefined" class="team-tab__count">{{ t.count }}</span>
        </button>
      </div>

      <Card class="team-panel">
        <template #content>
          <TeamRoster
            v-if="tab === 'roster'"
            :team="team"
            :members="members"
            :loading="loadingMembers"
            :adding="adding"
            @add="addMembers"
            @remove="removeMember"
            @open="openMemberDialog"
          />

          <div v-else-if="tab === 'coaches'" class="coaches">
            <p class="coaches__hint">
              Les coachs voient « Mon équipe » et peuvent télécharger les licences de leurs licenciés.
            </p>
            <div class="coaches__edit">
              <MultiSelect
                v-model="coachIds"
                :options="userOptions"
                option-label="label"
                option-value="value"
                filter
                placeholder="Choisir les coachs..."
                class="coaches__select"
              />
              <Button label="Enregistrer" icon="pi pi-check" size="small" :loading="savingCoaches" @click="saveCoaches" />
            </div>
          </div>

          <div v-else class="games">
            <div v-if="!games.length" class="team-empty">Aucun match pour cette équipe</div>
            <div v-for="game in games" :key="game.id" class="game-row">
              <span class="game-row__date">{{ formatDate(game.date) }}</span>
              <span class="game-row__opponent">{{ game.opponent }}</span>
              <Tag
                :value="game.venue === 'home' ? 'Domicile' : 'Extérieur'"
                :severity="game.venue === 'home' ? 'success' : 'secondary'"
                class="text-xs"
              />
              <span class="game-row__meta">{{ game.location || '—' }}{{ game.meetingTime ? ` · RDV ${game.meetingTime}` : '' }}</span>
            </div>
          </div>
        </template>
      </Card>
    </template>
  </div>
</template>

<script setup lang="ts">
import SkeletonLoader from '~/components/common/skeleton/SkeletonLoader.vue';
import ConfirmDeleteDialog from '~/components/dialogs/ConfirmDeleteDialog.vue';
import CreateUpdateTeamDialog from '~/components/dialogs/CreateUpdateTeamDialog.vue';
import MemberDetailsDialog from '~/components/dialogs/MemberDetailsDialog.vue';
import TeamRoster from '~/components/teams/TeamRoster.vue';
import { GameRepository } from '~/repository/game-repository';
import { MemberRepository } from '~/repository/member-repository';
import { TeamRepository } from '~/repository/team-repository';
import { UserRepository } from '~/repository/user-repository';
import type { Game } from '~/types/entity/Game';
import type { Member } from '~/types/entity/Member';
import type { Team } from '~/types/entity/Team';
import type { AppUser } from '~/types/entity/AppUser';
import { AppUserRole } from '~/types/entity/AppUser';

definePageMeta({
  middleware: 'auth-middleware',
  layout: 'dashboard',
  requiredRoles: [AppUserRole.SUPER_ADMIN],
  redirectTo: '/dashboard/calendar'
});

const route = useRoute();
const teamId = Number(route.params.id);
const { show } = useDialogManager();
const toast = usePVToastService();
const teamRepository = new TeamRepository();
const memberRepository = new MemberRepository();
const { selected: season, load: loadSeasons } = useSeasonFilter();

const team = ref<Team | null>(null);
const members = ref<Member[]>([]);
const games = ref<Game[]>([]);
const users = ref<AppUser[]>([]);
const coachIds = ref<number[]>([]);
const tab = ref<'roster' | 'coaches' | 'games'>('roster');
const loading = ref(true);
const loadingMembers = ref(false);
const adding = ref(false);
const savingCoaches = ref(false);

useHead({ title: computed(() => team.value ? `Équipe ${team.value.name}` : 'Équipe') });

const unpaid = computed(() => members.value.filter(m => !m.licensePaid).length);

const tabs = computed(() => [
  { key: 'roster' as const, label: 'Effectif', icon: 'pi pi-users', count: members.value.length },
  { key: 'coaches' as const, label: 'Coachs', icon: 'pi pi-user', count: team.value?.coaches?.length ?? 0 },
  { key: 'games' as const, label: 'Matchs', icon: 'pi pi-calendar', count: games.value.length },
]);

const userOptions = computed(() => users.value.map(u => ({ label: u.email, value: u.id })));

const formatDate = (iso: string) =>
  new Date(iso).toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit', month: 'short' });

// Pas d'endpoint « une équipe » : la liste porte déjà les compteurs de la
// saison, et il y a une poignée d'équipes — on y pioche celle de la route.
const loadTeam = async () => {
  const all = await teamRepository.getAll(season.value || undefined);
  team.value = all.find(t => t.id === teamId) ?? null;
  coachIds.value = (team.value?.coaches ?? []).map(c => c.id);
};

const loadMembers = async () => {
  loadingMembers.value = true;
  try {
    members.value = await memberRepository.getByTeam(teamId, season.value || undefined);
  } finally {
    loadingMembers.value = false;
  }
};

const applyRoster = (updated: Team & { members: Member[] }) => {
  members.value = updated.members;
  team.value = { ...(team.value as Team), ...updated };
};

const addMembers = async (memberIds: number[]) => {
  if (!memberIds.length) return;

  adding.value = true;
  try {
    const updated = await teamRepository.updateRoster(teamId, { add: memberIds, season: season.value || undefined });
    applyRoster(updated);

    // Le rattachement n'est pas saisonnier : un licencié sans licence pour la
    // saison affichée est bien rattaché mais reste invisible ici. Le dire.
    const missing = memberIds.filter(id => !updated.members.some(m => m.id === id));
    if (missing.length) {
      toast.add({
        severity: 'warn',
        summary: 'Ajouté hors effectif affiché',
        detail: `${missing.length} licencié(s) rattaché(s) mais sans licence ${season.value} : ils apparaîtront une fois leur licence validée.`,
        life: 7000,
      });
    }
  } catch (e: any) {
    toast.add({ severity: 'error', summary: 'Ajout impossible', detail: e?.data?.message || 'Réessayez plus tard.', life: 5000 });
  } finally {
    adding.value = false;
  }
};

const removeMember = async (member: Member) => {
  try {
    applyRoster(await teamRepository.updateRoster(teamId, { remove: [member.id], season: season.value || undefined }));
  } catch (e: any) {
    toast.add({ severity: 'error', summary: 'Retrait impossible', detail: e?.data?.message || 'Réessayez plus tard.', life: 5000 });
  }
};

const saveCoaches = async () => {
  if (!team.value) return;

  savingCoaches.value = true;
  try {
    team.value = { ...team.value, ...(await teamRepository.createUpdate(team.value.name, teamId, coachIds.value)) };
    toast.add({ severity: 'success', summary: 'Coachs enregistrés', life: 3000 });
  } catch (e: any) {
    toast.add({ severity: 'error', summary: 'Enregistrement impossible', detail: e?.data?.message || 'Réessayez plus tard.', life: 5000 });
  } finally {
    savingCoaches.value = false;
  }
};

const openEditDialog = () => {
  show({
    component: CreateUpdateTeamDialog,
    props: {
      team: team.value,
      users: users.value,
      onSubmit: async (values: { name: string; userIds: number[] }) => {
        team.value = { ...(team.value as Team), ...(await teamRepository.createUpdate(values.name, teamId, values.userIds)) };
        coachIds.value = values.userIds;
      }
    }
  });
};

const openMemberDialog = (member: Member) => {
  show({
    component: MemberDetailsDialog,
    props: {
      member,
      teams: team.value ? [team.value] : [],
      onSaved: loadMembers,
    }
  });
};

// Suppression douce : l'équipe sort des listes, ses matchs restent.
const confirmDelete = () => {
  show({
    component: ConfirmDeleteDialog,
    props: {
      header: "Supprimer l'équipe",
      message: `Supprimer « ${team.value?.name} » ? Ses licenciés et coachs en seront détachés.`
        + ' Les matchs déjà planifiés ou joués sont conservés.',
      onConfirm: async () => {
        await teamRepository.delete(teamId);
        await navigateTo('/dashboard/settings/teams');
      },
    },
  });
};

watch(season, async () => {
  await Promise.all([loadTeam(), loadMembers()]);
});

onMounted(async () => {
  try {
    await loadSeasons();
    await Promise.all([
      loadTeam(),
      loadMembers(),
      new GameRepository().getAll({ teamId }).then(g => { games.value = g; }),
      new UserRepository().getAll().then(u => { users.value = u; }),
    ]);
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.team-page {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.team-back {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.85rem;
  color: var(--p-text-muted-color);
  text-decoration: none;
  width: fit-content;
}

.team-back:hover {
  color: var(--p-primary-color);
}

.team-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}

.team-header__identity {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  min-width: 0;
}

.team-header__name {
  margin: 0;
  font-size: 1.6rem;
  line-height: 1.2;
}

.team-header__coaches {
  display: flex;
  gap: 0.35rem;
  flex-wrap: wrap;
}

.team-header__actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.team-metrics {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(8rem, 1fr));
  gap: 0.75rem;
}

.team-metric {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  padding: 0.75rem 1rem;
  border: 1px solid var(--p-surface-border);
  border-radius: 10px;
  background: var(--p-surface-card);
}

.team-metric--alert {
  border-color: var(--p-orange-400, #fb923c);
}

.team-metric__value {
  font-size: 1.5rem;
  font-weight: 700;
  line-height: 1.1;
  color: var(--p-text-color);
}

.team-metric__label {
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
}

/* Onglets : même patron que la fiche licencié (boutons, pas de lib) */
.team-tabs {
  display: flex;
  gap: 0.25rem;
  border-bottom: 1px solid var(--p-surface-border);
  overflow-x: auto;
}

.team-tab {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.6rem 0.9rem;
  border: none;
  border-bottom: 2px solid transparent;
  background: none;
  font: inherit;
  font-size: 0.9rem;
  color: var(--p-text-muted-color);
  cursor: pointer;
  white-space: nowrap;
}

.team-tab--active {
  color: var(--p-primary-color);
  border-bottom-color: var(--p-primary-color);
  font-weight: 600;
}

.team-tab__count {
  font-size: 0.75rem;
  padding: 0 0.4rem;
  border-radius: 999px;
  background: var(--p-surface-200, #e5e7eb);
  color: var(--p-text-color);
}

.team-panel :deep(.p-card-body) {
  padding: 1rem;
}

.coaches {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.coaches__hint {
  margin: 0;
  font-size: 0.85rem;
  color: var(--p-text-muted-color);
}

.coaches__edit {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.coaches__select {
  flex: 1 1 18rem;
}

.games {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.game-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem;
  border-radius: 8px;
  flex-wrap: wrap;
}

.game-row:nth-child(odd) {
  background: var(--p-surface-ground);
}

.game-row__date {
  font-weight: 600;
  min-width: 7rem;
}

.game-row__opponent {
  flex: 1;
  min-width: 8rem;
}

.game-row__meta {
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
}

.team-empty {
  padding: 2rem 1rem;
  text-align: center;
  color: var(--p-text-muted-color);
}

@media (max-width: 767px) {
  .team-header__name {
    font-size: 1.3rem;
  }

  .team-header__actions {
    width: 100%;
  }

  .team-header__actions :deep(.p-button) {
    flex: 1;
  }
}
</style>
