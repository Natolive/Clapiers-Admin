<template>
  <div>
    <Toolbar class="mb-4">
      <template #start>
        <Button label="Nouvelle équipe" icon="pi pi-plus" @click="openCreateDialog()" />
      </template>
    </Toolbar>

    <SkeletonLoader v-if="loading" type="table" />

    <Card v-else>
      <template #content>
        <TeamsDatatable
          :teams="teams"
          @team-updated="handleTeamUpdated"
          @team-created="handleTeamCreated"
        />
      </template>
    </Card>
  </div>
</template>

<script setup lang="ts">
import SkeletonLoader from '~/components/common/skeleton/SkeletonLoader.vue';
import TeamsDatatable from '~/components/datatables/TeamsDatatable.vue';
import CreateUpdateTeamDialog from '~/components/dialogs/CreateUpdateTeamDialog.vue';
import { TeamRepository } from '~/repository/team-repository';
import type { Team } from '~/types/entity/Team';

definePageMeta({
  middleware: 'auth-middleware',
  layout: 'dashboard'
});

const { isSuperAdmin } = useUserRole();
const { show } = useDialogManager();
const teamRepository = new TeamRepository();
const teams = ref<Team[]>([]);
const loading = ref(true);

// Handle team updates
const handleTeamUpdated = (updatedTeam: Team) => {
  const index = teams.value.findIndex(t => t.id === updatedTeam.id);
  if (index !== -1) {
    teams.value[index] = updatedTeam;
  }
};

// Handle team creation
const handleTeamCreated = (newTeam: Team) => {
  teams.value.push(newTeam);
};

// Open dialog for creating a new team
const openCreateDialog = () => {
  show({
    component: CreateUpdateTeamDialog,
    props: {
      team: null,
      onSubmit: async (values: { name: string }) => {
        const savedTeam = await teamRepository.createUpdate(values.name, null);
        handleTeamCreated(savedTeam);
      }
    }
  });
};

onMounted(async () => {
  if (!isSuperAdmin.value) {
    await navigateTo('/dashboard');
    return;
  }

  try {
    teams.value = await teamRepository.getAll();
  } finally {
    loading.value = false;
  }
});
</script>
