<template>
  <div>
    <h2 class="text-3xl font-bold mb-4">Équipes</h2>

    <Toolbar class="mb-4">
      <template #start>
        <Button label="Nouvelle équipe" icon="pi pi-plus" @click="openDialog()" />
      </template>
    </Toolbar>

    <SkeletonLoader v-if="loading" type="table" />

    <Card v-else>
      <template #content>
        <DataTable
          :value="teams"
          stripedRows
          paginator
          :rows="10"
          :rowsPerPageOptions="[5, 10, 20, 50]"
          responsiveLayout="scroll"
          class="p-datatable-sm"
        >
          <Column field="name" header="Nom" sortable style="width: 70%"></Column>
          <Column field="createdAt" header="Date de création" sortable style="width: 20%">
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
        </DataTable>
      </template>
    </Card>

  </div>
</template>

<script setup lang="ts">
import SkeletonLoader from '~/components/common/skeleton/SkeletonLoader.vue';
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

// Open dialog for create or edit
const openDialog = (team?: Team) => {
  show({
    component: CreateUpdateTeamDialog,
    props: {
      team: team || null,
      onSubmit: async (values: { name: string }) => {
        const savedTeam = await teamRepository.createUpdate(
          values.name,
          team?.id || null
        );

        if (team) {
          // Update existing team in list
          const index = teams.value.findIndex(t => t.id === team.id);
          if (index !== -1) {
            teams.value[index] = savedTeam;
          }
        } else {
          // Add new team to list
          teams.value.push(savedTeam);
        }
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
  }  finally {
    loading.value = false;
  }
});
</script>
