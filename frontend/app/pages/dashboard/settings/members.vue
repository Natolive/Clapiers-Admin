<template>
  <div>
    <Toolbar class="mb-4">
      <template #start>
        <Button label="Nouveau membre" icon="pi pi-plus" @click="openDialog()" />
      </template>
    </Toolbar>

    <SkeletonLoader v-if="loading" type="table" />

    <Card v-else>
      <template #content>
        <DataTable
          :value="members"
          stripedRows
          responsiveLayout="scroll"
          class="p-datatable-sm"
        >
          <Column field="firstName" header="Prénom" sortable style="width: 25%"></Column>
          <Column field="lastName" header="Nom" sortable style="width: 25%"></Column>
          <Column field="team.name" header="Équipe" sortable style="width: 30%"></Column>
          <Column field="createdAt" header="Date de création" sortable style="width: 15%">
            <template #body="slotProps">
              {{ new Date(slotProps.data.createdAt).toLocaleDateString('fr-FR') }}
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
    </Card>
  </div>
</template>

<script setup lang="ts">
import SkeletonLoader from '~/components/common/skeleton/SkeletonLoader.vue';
import CreateUpdateMemberDialog from '~/components/dialogs/CreateUpdateMemberDialog.vue';
import { MemberRepository } from '~/repository/member-repository';
import { TeamRepository } from '~/repository/team-repository';
import type { Member } from '~/types/entity/Member';
import type { Team } from '~/types/entity/Team';

definePageMeta({
  middleware: 'auth-middleware',
  layout: 'dashboard'
});

const { isSuperAdmin } = useUserRole();
const { show } = useDialogManager();
const memberRepository = new MemberRepository();
const teamRepository = new TeamRepository();
const members = ref<Member[]>([]);
const teams = ref<Team[]>([]);
const loading = ref(true);

// Open dialog for create or edit
const openDialog = (member?: Member) => {
  show({
    component: CreateUpdateMemberDialog,
    props: {
      member: member || null,
      teams: teams.value,
      onSubmit: async (values: { firstName: string; lastName: string; teamId: number }) => {
        const savedMember = await memberRepository.createUpdate(
          values.firstName,
          values.lastName,
          values.teamId,
          member?.id || null
        );

        if (member) {
          // Update existing member in list
          const index = members.value.findIndex(m => m.id === member.id);
          if (index !== -1) {
            members.value[index] = savedMember;
          }
        } else {
          // Add new member to list
          members.value.push(savedMember);
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
    [members.value, teams.value] = await Promise.all([
      memberRepository.getAll(),
      teamRepository.getAll()
    ]);
  } finally {
    loading.value = false;
  }
});
</script>
