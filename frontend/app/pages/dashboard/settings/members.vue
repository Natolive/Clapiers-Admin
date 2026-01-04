<template>
  <div>
    <Toolbar class="mb-4">
      <template #start>
        <Button label="Nouveau membre" icon="pi pi-plus" @click="openCreateDialog()" />
      </template>
    </Toolbar>

    <SkeletonLoader v-if="loading" type="table" />

    <Card v-else>
      <template #content>
        <MembersDatatable
          :members="members"
          :teams="teams"
          @member-updated="handleMemberUpdated"
          @member-created="handleMemberCreated"
        />
      </template>
    </Card>
  </div>
</template>

<script setup lang="ts">
import SkeletonLoader from '~/components/common/skeleton/SkeletonLoader.vue';
import MembersDatatable from '~/components/datatables/MembersDatatable.vue';
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

// Handle member updates
const handleMemberUpdated = (updatedMember: Member) => {
  const index = members.value.findIndex(m => m.id === updatedMember.id);
  if (index !== -1) {
    members.value[index] = updatedMember;
  }
};

// Handle member creation
const handleMemberCreated = (newMember: Member) => {
  members.value.push(newMember);
};

// Open dialog for creating a new member
const openCreateDialog = () => {
  show({
    component: CreateUpdateMemberDialog,
    props: {
      member: null,
      teams: teams.value,
      onSubmit: async (values: { firstName: string; lastName: string; phoneNumber: string; email: string; teamId: number }) => {
        const savedMember = await memberRepository.createUpdate(
          values.firstName,
          values.lastName,
          values.phoneNumber,
          values.email,
          values.teamId,
          null
        );
        handleMemberCreated(savedMember);
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
