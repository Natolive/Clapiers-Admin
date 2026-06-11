<template>
  <DataTable
    v-if="!isMobile"
    :value="teams"
    v-model:expandedRows="expandedRows"
    stripedRows
    tableStyle="min-width: 36rem"
    class="p-datatable-sm"
    dataKey="id"
    @row-expand="onRowExpand"
  >
    <template #empty>
      <div class="datatable-empty">
        <i class="pi pi-sitemap" />
        <span>Aucune équipe</span>
      </div>
    </template>
    <Column expander style="width: 5%" />
    <Column field="name" header="Nom" sortable style="width: 60%"></Column>
    <Column field="createdAt" header="Date de création" sortable style="width: 25%">
      <template #body="slotProps">
        {{ new Date(slotProps.data.createdAt).toLocaleDateString('fr-FR') }}
      </template>
    </Column>
    <Column header="Actions" style="width: 10%">
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
    <template #expansion="slotProps">
      <div class="p-3">
        <h5 class="mb-3">Licenciés de l'équipe</h5>
        <div v-if="loadingMembers[slotProps.data.id]" class="text-center">
          <i class="pi pi-spinner pi-spin" style="font-size: 2rem"></i>
        </div>
        <div v-else-if="teamMembers[slotProps.data.id]?.length">
          <DataTable
            :value="teamMembers[slotProps.data.id]"
            class="p-datatable-sm team-members-table"
            stripedRows
            @row-click="openMemberDialog($event.data, slotProps.data.id)"
          >
            <Column header="Licencié" sortable field="firstName">
              <template #body="memberProps">
                <div class="flex align-items-center gap-3">
                  <div @click.stop>
                    <MemberAvatar
                      :member="memberProps.data"
                      size="normal"
                      editable
                      @upload="(file: File) => onUploadProfilePicture(memberProps.data, file)"
                      @delete="onDeleteProfilePicture(memberProps.data)"
                    />
                  </div>
                  <span>{{ memberProps.data.firstName }} {{ memberProps.data.lastName }}</span>
                </div>
              </template>
            </Column>
            <Column field="createdAt" header="Date d'ajout" sortable>
              <template #body="memberProps">
                {{ new Date(memberProps.data.createdAt).toLocaleDateString('fr-FR') }}
              </template>
            </Column>
          </DataTable>
        </div>
        <div v-else class="text-center text-muted">
          <p>Aucun licencié dans cette équipe</p>
        </div>
      </div>
    </template>
  </DataTable>

  <!-- Mobile : cartes dépliables -->
  <div v-else class="team-cards">
    <template v-if="teams.length">
      <div v-for="team in teams" :key="team.id" class="team-card">
        <div class="team-card__head" @click="toggleExpand(team)">
          <i
            class="pi team-card__chevron"
            :class="isExpanded(team.id) ? 'pi-chevron-down' : 'pi-chevron-right'"
          />
          <div class="team-card__main">
            <span class="team-card__name">{{ team.name }}</span>
            <span class="team-card__meta">Créée le {{ new Date(team.createdAt).toLocaleDateString('fr-FR') }}</span>
          </div>
          <Button
            icon="pi pi-pencil"
            severity="secondary"
            text
            rounded
            @click.stop="openDialog(team)"
          />
        </div>

        <div v-if="isExpanded(team.id)" class="team-card__members">
          <div v-if="loadingMembers[team.id]" class="team-card__members-loading">
            <i class="pi pi-spinner pi-spin" />
          </div>
          <template v-else-if="teamMembers[team.id]?.length">
            <div
              v-for="member in teamMembers[team.id]"
              :key="member.id"
              class="team-member-row"
              @click="openMemberDialog(member, team.id)"
            >
              <MemberAvatar :member="member" size="normal" />
              <span class="team-member-row__name">{{ member.firstName }} {{ member.lastName }}</span>
              <i class="pi pi-chevron-right team-member-row__chevron" />
            </div>
          </template>
          <span v-else class="team-card__members-empty">Aucun licencié dans cette équipe</span>
        </div>
      </div>
    </template>

    <div v-else class="team-cards__empty">
      <i class="pi pi-sitemap" />
      <span>Aucune équipe</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import CreateUpdateTeamDialog from '~/components/dialogs/CreateUpdateTeamDialog.vue';
import MemberDetailsDialog from '~/components/dialogs/MemberDetailsDialog.vue';
import MemberAvatar from '~/components/common/MemberAvatar.vue';
import { MemberRepository } from '~/repository/member-repository';
import type { Team } from '~/types/entity/Team';
import type { Member } from '~/types/entity/Member';

const props = defineProps<{
  teams: Team[]
}>();

const emit = defineEmits<{
  teamUpdated: [team: Team]
  teamCreated: [team: Team]
}>();

const { show } = useDialogManager();
const memberRepository = new MemberRepository();
const isMobile = useIsMobile();
const expandedRows = ref<Team[]>([]);
const expandedTeamIds = ref(new Set<number>());
const teamMembers = ref<Record<number, Member[]>>({});
const loadingMembers = ref<Record<number, boolean>>({});

// Un licencié peut être dans plusieurs équipes dépliées : on met à jour tous les caches
const replaceMemberInCaches = (updated: Member) => {
  for (const members of Object.values(teamMembers.value)) {
    const idx = members.findIndex(m => m.id === updated.id);
    if (idx !== -1) members[idx] = updated;
  }
};

const onUploadProfilePicture = async (member: Member, file: File) => {
  replaceMemberInCaches(await memberRepository.uploadProfilePicture(member.id, file));
};

const onDeleteProfilePicture = async (member: Member) => {
  replaceMemberInCaches(await memberRepository.deleteProfilePicture(member.id));
};

// Chargement partagé entre l'expansion du DataTable (desktop) et les cartes (mobile)
const loadTeamMembers = async (teamId: number) => {
  if (teamMembers.value[teamId]) {
    return;
  }

  loadingMembers.value[teamId] = true;

  try {
    teamMembers.value[teamId] = await memberRepository.getByTeam(teamId);
  } catch (error) {
    console.error('Error loading members:', error);
    teamMembers.value[teamId] = [];
  } finally {
    loadingMembers.value[teamId] = false;
  }
};

const onRowExpand = (event: { data: Team }) => loadTeamMembers(event.data.id);

const isExpanded = (teamId: number) => expandedTeamIds.value.has(teamId);

const toggleExpand = (team: Team) => {
  const next = new Set(expandedTeamIds.value);
  if (next.has(team.id)) {
    next.delete(team.id);
  } else {
    next.add(team.id);
    loadTeamMembers(team.id);
  }
  expandedTeamIds.value = next;
};

// Fiche/édition d'un licencié depuis une équipe dépliée
const openMemberDialog = (member: Member, teamId: number) => {
  show({
    component: MemberDetailsDialog,
    props: {
      member,
      teams: props.teams,
      onSaved: () => {
        // Le licencié a pu changer d'équipe : on recharge la liste
        delete teamMembers.value[teamId];
        loadTeamMembers(teamId);
      },
    }
  });
};

// Open dialog for create or edit
const openDialog = (team?: Team) => {
  show({
    component: CreateUpdateTeamDialog,
    props: {
      team: team || null,
      onSubmit: async (values: { name: string }) => {
        const { TeamRepository } = await import('~/repository/team-repository');
        const teamRepository = new TeamRepository();
        const savedTeam = await teamRepository.createUpdate(
          values.name,
          team?.id || null
        );

        if (team) {
          emit('teamUpdated', savedTeam);
        } else {
          emit('teamCreated', savedTeam);
        }
      }
    }
  });
};
</script>

<style scoped>
.datatable-empty,
.team-cards__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  padding: 2.5rem 1rem;
  color: var(--p-text-muted-color);
}

.datatable-empty i,
.team-cards__empty i {
  font-size: 2rem;
  opacity: 0.5;
}

/* Cartes mobile */
.team-cards {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.team-card {
  border: 1px solid var(--p-surface-border);
  border-radius: 10px;
  background: var(--p-surface-card);
  overflow: hidden;
}

.team-card__head {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem 0.75rem;
  cursor: pointer;
  user-select: none;
}

.team-card__head:active {
  background: var(--p-surface-hover);
}

.team-card__chevron {
  color: var(--p-text-muted-color);
  font-size: 0.8rem;
  flex-shrink: 0;
}

.team-card__main {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  flex: 1;
  min-width: 0;
}

.team-card__name {
  font-weight: 600;
  color: var(--p-text-color);
}

.team-card__meta {
  font-size: 0.75rem;
  color: var(--p-text-muted-color);
}

.team-card__members {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  padding: 0.75rem;
  border-top: 1px solid var(--p-surface-border);
  background: var(--p-surface-ground);
}

.team-card__members-loading {
  text-align: center;
  padding: 0.5rem;
  color: var(--p-text-muted-color);
}

.team-card__members-empty {
  font-size: 0.8rem;
  color: var(--p-text-muted-color);
  text-align: center;
  padding: 0.5rem;
}

.team-member-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.875rem;
  cursor: pointer;
  border-radius: 8px;
  padding: 0.25rem;
  margin: -0.25rem;
}

.team-member-row:active {
  background: var(--p-surface-hover);
}

.team-member-row__name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.team-member-row__chevron {
  color: var(--p-text-muted-color);
  font-size: 0.7rem;
  flex-shrink: 0;
}

/* Lignes cliquables du tableau de licenciés (expansion desktop) */
.team-members-table :deep(.p-datatable-tbody > tr) {
  cursor: pointer;
}
</style>
