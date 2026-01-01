<template>
  <DataTable
    :value="teams"
    v-model:expandedRows="expandedRows"
    stripedRows
    responsiveLayout="scroll"
    class="p-datatable-sm"
    dataKey="id"
    @row-expand="onRowExpand"
  >
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
        <h5 class="mb-3">Membres de l'équipe</h5>
        <div v-if="loadingMembers[slotProps.data.id]" class="text-center">
          <i class="pi pi-spinner pi-spin" style="font-size: 2rem"></i>
        </div>
        <div v-else-if="teamMembers[slotProps.data.id]?.length">
          <DataTable
            :value="teamMembers[slotProps.data.id]"
            class="p-datatable-sm"
            stripedRows
          >
            <Column header="Membre" sortable field="firstName">
              <template #body="memberProps">
                <div class="flex align-items-center gap-3">
                  <MemberAvatar :member="memberProps.data" size="normal" />
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
          <p>Aucun membre dans cette équipe</p>
        </div>
      </div>
    </template>
  </DataTable>
</template>

<script setup lang="ts">
import CreateUpdateTeamDialog from '~/components/dialogs/CreateUpdateTeamDialog.vue';
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
const expandedRows = ref<Team[]>([]);
const teamMembers = ref<Record<number, Member[]>>({});
const loadingMembers = ref<Record<number, boolean>>({});

// Handle row expansion to load members
const onRowExpand = async (event: { data: Team }) => {
  const teamId = event.data.id;

  // Skip if already loaded
  if (teamMembers.value[teamId]) {
    return;
  }

  loadingMembers.value[teamId] = true;

  try {
    const members = await memberRepository.getByTeam(teamId);
    teamMembers.value[teamId] = members;
  } catch (error) {
    console.error('Error loading members:', error);
    teamMembers.value[teamId] = [];
  } finally {
    loadingMembers.value[teamId] = false;
  }
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
